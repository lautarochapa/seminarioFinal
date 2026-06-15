<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['family_group_id', 'user_id', 'report_type', 'period_from', 'period_to', 'data_json', 'created_at'];

    protected $casts = ['period_from' => 'date', 'period_to' => 'date', 'data_json' => 'array', 'created_at' => 'datetime'];

    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
    public function user() { return $this->belongsTo(User::class); }
}
