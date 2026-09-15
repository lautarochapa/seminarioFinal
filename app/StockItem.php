<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'family_group_id',
        'product_id',
        'stock_location_id',
        'quantity',
        'unit_id',
        'purchase_date',
        'expiration_date',
        'opened_at',
        'is_open',
        'estimated_purchase_price',
        'status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'purchase_date' => 'date',
        'expiration_date' => 'date',
        'opened_at' => 'datetime',
        'is_open' => 'boolean',
        'estimated_purchase_price' => 'decimal:2',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(StockLocation::class, 'stock_location_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_id');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function alerts()
    {
        return $this->hasMany(StockAlert::class);
    }

    /**
     * Resuelve el lote de stock activo en el que debe acumularse un alta.
     *
     * Un "lote" es la combinacion grupo + producto + unidad + vencimiento, y la
     * ubicacion cuando se indica una. Criterio unico para las tres vias de alta
     * (scanner, "Mi cocina" y producto manual):
     *
     *  - Con ubicacion: coincide grupo+producto+unidad+vencimiento+ubicacion.
     *  - Sin ubicacion: primero se busca una fila sin ubicacion; si no hay y
     *    existe UN solo lote candidato (cualquier ubicacion) se acumula ahi, en
     *    vez de crear una fila huerfana.
     *  - Lotes con distinta ubicacion o distinto vencimiento se mantienen
     *    separados: son lotes reales distintos y la UI los muestra por separado.
     *
     * NO deduplica por nombre.
     */
    public static function resolveActiveLot(int $groupId, int $productId, ?int $locationId, int $unitId, $expirationDate): ?self
    {
        $expDate = $expirationDate instanceof \DateTimeInterface
            ? $expirationDate->format('Y-m-d')
            : ($expirationDate !== null ? (string) $expirationDate : null);

        $base = function () use ($groupId, $productId, $unitId, $expDate) {
            return static::where('family_group_id', $groupId)
                ->where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($expDate) {
                    $expDate === null
                        ? $q->whereNull('expiration_date')
                        : $q->whereDate('expiration_date', $expDate);
                });
        };

        if ($locationId !== null) {
            return $base()->where('stock_location_id', $locationId)->first();
        }

        $withoutLocation = $base()->whereNull('stock_location_id')->first();
        if ($withoutLocation) {
            return $withoutLocation;
        }

        $candidates = $base()->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
