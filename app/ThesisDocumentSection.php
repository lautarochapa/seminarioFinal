<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThesisDocumentSection extends Model
{
    use SoftDeletes;

    protected $fillable = ['document_id', 'parent_id', 'title', 'content', 'sort_order', 'status'];

    public function document() { return $this->belongsTo(ThesisDocument::class, 'document_id'); }
    public function parent() { return $this->belongsTo(ThesisDocumentSection::class, 'parent_id'); }
    public function children() { return $this->hasMany(ThesisDocumentSection::class, 'parent_id'); }
    public function comments() { return $this->hasMany(ThesisComment::class, 'section_id'); }
}
