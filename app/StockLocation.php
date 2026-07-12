<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'family_group_id',
        'name',
        'type',
        'status',
    ];

    public function familyGroup()
    {
        return $this->belongsTo(FamilyGroup::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }
}
