<?php

namespace App\Enums;

/**
 * The short status list from the proposal. The actual REC decision is
 * recorded separately (Phase 3); a status only says where the work is.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ForScreening = 'for_screening';
    case Incomplete = 'incomplete';
    case ForReview = 'for_review';
    case AwaitingDecision = 'awaiting_decision';
    case ForRevision = 'for_revision';
    case DecisionReleased = 'decision_released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::ForScreening => 'For Screening',
            self::Incomplete => 'Incomplete',
            self::ForReview => 'For Review',
            self::AwaitingDecision => 'Awaiting REC Decision',
            self::ForRevision => 'For Revision',
            self::DecisionReleased => 'Decision Released',
        };
    }

    /**
     * Bootstrap badge classes.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Submitted => 'text-bg-info',
            self::ForScreening => 'text-bg-primary',
            self::Incomplete => 'text-bg-warning',
            self::ForReview => 'text-bg-primary',
            self::AwaitingDecision => 'text-bg-dark',
            self::ForRevision => 'text-bg-warning',
            self::DecisionReleased => 'text-bg-success',
        };
    }

    /**
     * Allowed moves. Later phases extend this map.
     *
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted],
            // A revised version may skip screening and go straight back to
            // review or to the decision, as UCUREC requires (Secretariat routes it).
            self::Submitted => [self::ForScreening, self::ForReview, self::AwaitingDecision],
            self::ForScreening => [self::Incomplete, self::ForReview],
            self::Incomplete => [self::Submitted],
            self::ForReview => [self::AwaitingDecision],
            self::AwaitingDecision => [self::ForRevision, self::DecisionReleased],
            self::ForRevision => [self::Submitted],
            self::DecisionReleased => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->nextStatuses(), true);
    }

    /**
     * Statuses in which the researcher may edit details and upload files.
     */
    public function isEditableByResearcher(): bool
    {
        return in_array($this, [self::Draft, self::Incomplete, self::ForRevision], true);
    }
}
