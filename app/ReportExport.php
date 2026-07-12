<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'family_group_id', 'report_type', 'file_url', 'format', 'status', 'created_at', 'finished_at'];

    protected $casts = ['created_at' => 'datetime', 'finished_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function familyGroup() { return $this->belongsTo(FamilyGroup::class); }
}
