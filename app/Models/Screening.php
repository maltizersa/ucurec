<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Secretariat's completeness check of one submitted document set.
 * Completeness alone never means ethical approval.
 */
class Screening extends Model
{
    public const RESULT_COMPLETE = 'complete';

    public const RESULT_INCOMPLETE = 'incomplete';

    public const STATE_PRESENT = 'present';

    public const STATE_MISSING = 'missing';

    public const STATE_NOT_APPLICABLE = 'not_applicable';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
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

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function isComplete(): bool
    {
        return $this->result === self::RESULT_COMPLETE;
    }

    /**
     * @return list<array{key: string, label: string, state: string, note: ?string}>
     */
    public function missingItems(): array
    {
        return array_values(array_filter(
            $this->checklist,
            fn (array $item) => $item['state'] === self::STATE_MISSING,
        ));
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_PRESENT => 'Present',
            self::STATE_MISSING => 'Missing',
            self::STATE_NOT_APPLICABLE => 'Not applicable',
            default => $state,
        };
    }
}
