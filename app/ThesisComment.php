<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ThesisComment extends Model
{
    protected $fillable = ['document_id', 'section_id', 'user_id', 'comment', 'status'];

    public function document() { return $this->belongsTo(ThesisDocument::class, 'document_id'); }
    public function section() { return $this->belongsTo(ThesisDocumentSection::class, 'section_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
