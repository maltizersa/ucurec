<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionStatus;
use App\Enums\Role;
use App\Exceptions\InvalidStatusTransition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    /**
     * Only the short record fields; status, reference number and dates
     * change through the workflow methods.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'principal_investigator',
        'pi_contact',
        'adviser',
        'college_program',
        'research_type',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
            'last_submitted_at' => 'datetime',
        ];
    }

    public function researcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'researcher_id');
    }

    public function documentSets(): HasMany
    {
        return $this->hasMany(DocumentSet::class)->orderBy('version');
    }

    public function draftSet(): HasOne
    {
        return $this->hasOne(DocumentSet::class)->whereNull('submitted_at');
    }

    public function latestSubmittedSet(): HasOne
    {
        return $this->hasOne(DocumentSet::class)->ofMany(
            ['version' => 'max'],
            fn (Builder $query) => $query->whereNotNull('submitted_at'),
        );
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class)->latest('id');
    }

    public function latestScreening(): HasOne
    {
        return $this->hasOne(Screening::class)->latestOfMany();
    }

    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class)->orderBy('id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class)->latest('id');
    }

    /**
     * The decision being prepared or waiting for the Chair, if any.
     */
    public function openDecision(): HasOne
    {
        return $this->hasOne(Decision::class)->where('status', '!=', DecisionStatus::Released->value);
    }

    /**
     * When the application entered its current status.
     */
    public function latestStatusChange(): HasOne
    {
        return $this->hasOne(ApplicationStatusHistory::class)->latestOfMany();
    }

    public function latestReleasedDecision(): HasOne
    {
        return $this->hasOne(Decision::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('status', DecisionStatus::Released->value),
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('id');
    }

    /**
     * Move to another status, enforcing the allowed transitions and
     * recording the change in the status history.
     */
    public function transitionTo(ApplicationStatus $to, ?User $by, ?string $note = null): void
    {
        $from = $this->status;

        if ($from !== null && ! $from->canMoveTo($to)) {
            throw new InvalidStatusTransition($from, $to);
        }

        $this->status = $to;
        $this->save();

        $this->statusHistories()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $by?->id,
            'note' => $note,
        ]);

        AuditLog::record('application.status_changed', $this, "Status changed to {$to->label()}.", [
            'from' => $from?->value,
            'to' => $to->value,
        ], $by);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->researcher_id === $user->id;
    }

    public function displayReference(): string
    {
        return $this->reference_no ?? 'Draft #'.$this->id;
    }

    /**
     * Applications the user may see in lists.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        match (true) {
            $user->role === Role::Researcher => $query->where('researcher_id', $user->id),
            $user->hasRole(Role::Secretariat, Role::Chair) => $query->where('status', '!=', ApplicationStatus::Draft->value),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
