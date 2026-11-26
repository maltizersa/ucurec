<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\FileSize;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One file inside a document set. The file lives on the private
 * "documents" disk under a random name; `original_name` is display only.
 */
class Document extends Model
{
    protected $guarded = ['id'];

    public function documentSet(): BelongsTo
    {
        return $this->belongsTo(DocumentSet::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'carried_from_id');
    }

    public function categoryLabel(): string
    {
        return config("ucurec.document_categories.{$this->category}.label", ucfirst(str_replace('_', ' ', $this->category)));
    }

    public function humanSize(): string
    {
        return FileSize::format($this->size);
    }
}
