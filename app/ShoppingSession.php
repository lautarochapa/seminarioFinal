<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShoppingSession extends Model
{
    protected $fillable = ['shopping_list_id', 'family_group_id', 'user_id', 'supermarket_branch_id', 'started_at', 'finished_at', 'status'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function shoppingList() { return $this->belongsTo(ShoppingList::class); }
    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function branch() { return $this->belongsTo(SupermarketBranch::class, 'supermarket_branch_id'); }
    public function scans() { return $this->hasMany(ShoppingSessionScan::class); }
}
