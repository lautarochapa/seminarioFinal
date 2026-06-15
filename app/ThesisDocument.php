<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThesisDocument extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'description', 'status', 'created_by', 'updated_by'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function sections() { return $this->hasMany(ThesisDocumentSection::class, 'document_id'); }
    public function versions() { return $this->hasMany(ThesisDocumentVersion::class, 'document_id'); }
    public function comments() { return $this->hasMany(ThesisComment::class, 'document_id'); }
}
