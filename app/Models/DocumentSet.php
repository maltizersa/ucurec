<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A numbered set of documents (v1 = initial submission, v2+ = resubmissions).
 * Once submitted_at is set the set is locked: its files never change.
 */
class DocumentSet extends Model
{
    public const KIND_INITIAL = 'initial';

    public const KIND_RESUBMISSION = 'resubmission';

    public const KIND_REVISION = 'revision';

    protected $fillable = ['version', 'kind', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->orderBy('category')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function isDraft(): bool
    {
        return $this->submitted_at === null;
    }

    public function label(): string
    {
        return 'Version '.$this->version;
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_INITIAL => 'Initial submission',
            self::KIND_RESUBMISSION => 'Resubmission (completed requirements)',
            self::KIND_REVISION => 'Revised submission',
            default => ucfirst($this->kind),
        };
    }

    /**
     * @return list<string> category keys present in this set
     */
    public function categories(): array
    {
        return $this->documents->pluck('category')->unique()->values()->all();
    }
}
