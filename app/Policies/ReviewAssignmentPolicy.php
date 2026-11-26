<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\ReviewAssignment;
use App\Models\User;

/**
 * Secretariat proposes and cancels; the Chair approves (authorizes) or
 * rejects proposals; the assigned reviewer responds and reviews.
 */
class ReviewAssignmentPolicy
{
    /**
     * The reviewer's own assignment page. Proposals the Chair has not
     * authorized are not shown to the reviewer.
     */
    public function view(User $user, ReviewAssignment $assignment): bool
    {
        return $this->isOwn($user, $assignment)
            && ! in_array($assignment->status, [AssignmentStatus::Proposed, AssignmentStatus::NotAuthorized], true);
    }

    public function approve(User $user, ReviewAssignment $assignment): bool
    {
        return $user->role === Role::Chair && $assignment->status === AssignmentStatus::Proposed;
    }

    public function reject(User $user, ReviewAssignment $assignment): bool
    {
        return $this->approve($user, $assignment);
    }

    public function cancel(User $user, ReviewAssignment $assignment): bool
    {
        return $user->role === Role::Secretariat && $assignment->status->isOpen();
    }

    /**
     * Accept or decline, with the conflict-of-interest declaration.
     */
    public function respond(User $user, ReviewAssignment $assignment): bool
    {
        return $this->isOwn($user, $assignment) && $assignment->status === AssignmentStatus::Authorized;
    }

    /**
     * Upload or remove review forms and submit the recommendation.
     */
    public function review(User $user, ReviewAssignment $assignment): bool
    {
        return $this->isOwn($user, $assignment) && $assignment->status === AssignmentStatus::Accepted;
    }

    private function isOwn(User $user, ReviewAssignment $assignment): bool
    {
        return $user->role === Role::Reviewer && $assignment->reviewer_id === $user->id;
    }
}
