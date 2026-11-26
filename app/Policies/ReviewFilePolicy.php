<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\ReviewFile;
use App\Models\User;

/**
 * Review forms are internal Committee records: the Secretariat, the Chair
 * and the reviewer who wrote them. Never researchers or other reviewers.
 */
class ReviewFilePolicy
{
    public function download(User $user, ReviewFile $file): bool
    {
        $assignment = $file->assignment;

        if ($user->hasRole(Role::Secretariat, Role::Chair)) {
            return true;
        }

        return $user->role === Role::Reviewer
            && $assignment->reviewer_id === $user->id
            && $assignment->status->grantsFileAccess();
    }

    public function delete(User $user, ReviewFile $file): bool
    {
        $assignment = $file->assignment;

        return $user->role === Role::Reviewer
            && $assignment->reviewer_id === $user->id
            && $assignment->status === AssignmentStatus::Accepted;
    }
}
