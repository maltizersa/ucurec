<?php

namespace App\Models;

use App\Support\FileSize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A completed review form uploaded by the reviewer. Internal to the
 * Committee: never shown to researchers.
 */
class ReviewFile extends Model
{
    protected $guarded = ['id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class, 'review_assignment_id');
    }

    public function formLabel(): string
    {
        return config("ucurec.review_form_types.{$this->form_type}", $this->form_type);
    }

    public function humanSize(): string
    {
        return FileSize::format($this->size);
    }
}
