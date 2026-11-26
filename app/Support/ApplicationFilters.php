<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Search and filters shared by the applications list and the CSV
 * register, so the export always matches what is on screen.
 */
class ApplicationFilters
{
    /** @var array<string, mixed> */
    public array $values;

    public function __construct(Request $request)
    {
        $this->values = [
            'q' => trim((string) $request->query('q')),
            'status' => ApplicationStatus::tryFrom((string) $request->query('status'))?->value,
            'college' => in_array($request->query('college'), config('ucurec.colleges'), true) ? $request->query('college') : null,
            'type' => in_array($request->query('type'), config('ucurec.research_types'), true) ? $request->query('type') : null,
            'route' => array_key_exists((string) $request->query('route'), config('ucurec.review_routes')) ? $request->query('route') : null,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'overdue' => $request->boolean('overdue'),
            'sort' => in_array($request->query('sort'), ['updated', 'submitted', 'reference'], true) ? $request->query('sort') : 'updated',
        ];
    }

    public function apply(Builder $query): Builder
    {
        $v = $this->values;

        return $query
            ->when($v['q'] !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('reference_no', 'like', "%{$v['q']}%")
                ->orWhere('title', 'like', "%{$v['q']}%")
                ->orWhere('principal_investigator', 'like', "%{$v['q']}%")
                ->orWhere('adviser', 'like', "%{$v['q']}%")))
            ->when($v['status'], fn (Builder $q) => $q->where('status', $v['status']))
            ->when($v['college'], fn (Builder $q) => $q->where('college_program', $v['college']))
            ->when($v['type'], fn (Builder $q) => $q->where('research_type', $v['type']))
            ->when($v['route'], fn (Builder $q) => $q->where('review_route', $v['route']))
            ->when($v['from'], fn (Builder $q) => $q->whereDate('submitted_at', '>=', $v['from']))
            ->when($v['to'], fn (Builder $q) => $q->whereDate('submitted_at', '<=', $v['to']))
            ->when($v['overdue'], fn (Builder $q) => $q->whereHas('reviewAssignments', fn (Builder $r) => $r->overdue()))
            ->when($v['sort'] === 'submitted', fn (Builder $q) => $q->orderByDesc('submitted_at'))
            ->when($v['sort'] === 'reference', fn (Builder $q) => $q->orderBy('reference_no'))
            ->when($v['sort'] === 'updated', fn (Builder $q) => $q->orderByDesc('updated_at'))
            ->orderByDesc('id');
    }

    public function isFiltered(): bool
    {
        return collect($this->values)->except('sort')->filter()->isNotEmpty();
    }

    /**
     * Current filters as query parameters (for the export link).
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return array_filter($this->values, fn ($value) => $value !== null && $value !== '' && $value !== false);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
