<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupplementType extends Model
{
    protected $fillable = ['code', 'name', 'description', 'status'];

    public function userSupplements() { return $this->hasMany(UserSupplement::class); }
}
