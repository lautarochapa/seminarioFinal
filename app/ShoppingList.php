<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingList extends Model
{
    use SoftDeletes;

    // Lifecycle: DRAFT -> ACTIVE -> IN_PROGRESS -> COMPLETED, with ACTIVE/IN_PROGRESS -> CANCELLED,
    // and COMPLETED -> ACTIVE only via explicit reopen. See allowedTransitions() below — this is the
    // single source of truth for valid status values and transitions across web, mobile and backend.
    const STATUS_DRAFT       = 'draft';
    const STATUS_ACTIVE      = 'active';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_CANCELLED   = 'cancelled';

    const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    const STATUS_LABELS = [
        self::STATUS_DRAFT       => 'Borrador',
        self::STATUS_ACTIVE      => 'Lista para comprar',
        self::STATUS_IN_PROGRESS => 'En compra',
        self::STATUS_COMPLETED   => 'Completada',
        self::STATUS_CANCELLED   => 'Cancelada',
    ];

    protected $fillable = ['family_group_id', 'meal_plan_id', 'created_by', 'source_type', 'status', 'estimated_total', 'selected_supermarket_branch_id', 'optimization_mode'];

    protected $casts = ['estimated_total' => 'decimal:2'];

    public static function allowedTransitions(): array
    {
        return [
            // ACTIVE -> COMPLETED is allowed directly (not just via IN_PROGRESS) because not every
            // purchase goes through the barcode-scanning session: a list can also be finalized after
            // simply checking items off as purchased, without an explicit "start" step.
            self::STATUS_DRAFT       => [self::STATUS_ACTIVE, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE      => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_ACTIVE],
            self::STATUS_COMPLETED   => [self::STATUS_ACTIVE],
            self::STATUS_CANCELLED   => [],
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        if ($status === $this->status) {
            return true;
        }

        return in_array($status, self::allowedTransitions()[$this->status] ?? [], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function mealPlan() { return $this->belongsTo(MealPlan::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function selectedBranch() { return $this->belongsTo(SupermarketBranch::class, 'selected_supermarket_branch_id'); }
    public function items() { return $this->hasMany(ShoppingListItem::class); }
    public function sessions() { return $this->hasMany(ShoppingSession::class); }
    public function purchases() { return $this->hasMany(Purchase::class); }
    public function budgetMovements() { return $this->hasMany(BudgetMovement::class, 'related_shopping_list_id'); }
}
