<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThesisDocumentVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['document_id', 'version_number', 'content_snapshot', 'created_by', 'created_at'];

    protected $casts = ['content_snapshot' => 'array', 'created_at' => 'datetime'];

    public function document() { return $this->belongsTo(ThesisDocument::class, 'document_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
