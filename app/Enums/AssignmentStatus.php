<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Proposed = 'proposed';             // by the Secretariat, waiting for the Chair
    case NotAuthorized = 'not_authorized';  // the Chair did not authorize the proposal
    case Authorized = 'authorized';         // waiting for the reviewer to respond
    case Accepted = 'accepted';             // no conflict declared; reviewing
    case Declined = 'declined';
    case Conflicted = 'conflicted';         // conflict declared; blocked from the files
    case Submitted = 'submitted';           // review forms and recommendation submitted
    case Cancelled = 'cancelled';           // withdrawn by the Secretariat (e.g. reassigned)

    public function label(): string
    {
        return match ($this) {
            self::Proposed => 'Awaiting Chair authorization',
            self::NotAuthorized => 'Not authorized',
            self::Authorized => 'Awaiting reviewer response',
            self::Accepted => 'In review',
            self::Declined => 'Declined',
            self::Conflicted => 'Conflict declared',
            self::Submitted => 'Review submitted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Proposed => 'text-bg-info',
            self::Authorized => 'text-bg-warning',
            self::Accepted => 'text-bg-primary',
            self::Submitted => 'text-bg-success',
            self::Conflicted => 'text-bg-danger',
            self::NotAuthorized, self::Declined, self::Cancelled => 'text-bg-secondary',
        };
    }

    /**
     * Still in progress: blocks forwarding the application for a decision.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Proposed, self::Authorized, self::Accepted], true);
    }

    /**
     * The reviewer may open the assigned files.
     */
    public function grantsFileAccess(): bool
    {
        return in_array($this, [self::Accepted, self::Submitted], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Proposed->value, self::Authorized->value, self::Accepted->value];
    }
}
