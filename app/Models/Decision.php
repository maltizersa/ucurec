<?php

namespace App\Models;

use App\Enums\DecisionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The REC decision for one reviewed version. Recorded by the Secretariat,
 * confirmed for release by the Chairperson. Researchers see it only after
 * release, and never the internal basis or minutes.
 */
class Decision extends Model
{
    public const FILE_LETTER = 'letter';

    public const FILE_COMMENTS = 'comments';

    public const FILE_MINUTES = 'minutes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DecisionStatus::class,
            'decision_date' => 'date',
            'approval_date' => 'date',
            'expiry_date' => 'date',
            'sent_for_confirmation_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'released_at' => 'datetime',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DecisionFile::class)->orderBy('kind')->orderBy('id');
    }

    /**
     * @return array{label: string, outcome: string, approval: bool}
     */
    public function categoryConfig(): array
    {
        return config("ucurec.decision_categories.{$this->category}")
            ?? ['label' => $this->category, 'outcome' => 'final', 'approval' => false];
    }

    public function categoryLabel(): string
    {
        return $this->categoryConfig()['label'];
    }

    public function requiresRevision(): bool
    {
        return $this->categoryConfig()['outcome'] === 'revision';
    }

    public function isApproval(): bool
    {
        return (bool) ($this->categoryConfig()['approval'] ?? false);
    }

    public function isReleased(): bool
    {
        return $this->status === DecisionStatus::Released;
    }

    public static function fileKindLabel(string $kind): string
    {
        return match ($kind) {
            self::FILE_LETTER => 'Signed official letter / clearance',
            self::FILE_COMMENTS => 'Consolidated comments',
            self::FILE_MINUTES => 'Decision record / minutes extract (internal)',
            default => $kind,
        };
    }

    /**
     * File kinds a researcher may download once the decision is released.
     *
     * @return list<string>
     */
    public static function releasableKinds(): array
    {
        return [self::FILE_LETTER, self::FILE_COMMENTS];
    }
}
