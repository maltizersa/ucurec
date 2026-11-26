<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reviewer assigned to one exact document set of an application.
 * A recommendation is advice to the Committee; it never becomes the
 * REC decision by itself.
 */
class ReviewAssignment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'due_at' => 'date',
            'authorized_at' => 'datetime',
            'responded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'has_conflict' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentSet(): BelongsTo
    {
        return $this->belongsTo(DocumentSet::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ReviewFile::class)->orderBy('form_type')->orderBy('id');
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [AssignmentStatus::Authorized, AssignmentStatus::Accepted], true)
            && $this->due_at->lt(today());
    }

    public function recommendationLabel(): ?string
    {
        return $this->recommendation
            ? config("ucurec.review_recommendations.{$this->recommendation}", $this->recommendation)
            : null;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', AssignmentStatus::openValues());
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', [AssignmentStatus::Authorized->value, AssignmentStatus::Accepted->value])
            ->whereDate('due_at', '<', today());
    }
}
