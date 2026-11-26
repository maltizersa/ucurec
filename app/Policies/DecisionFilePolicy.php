<?php

namespace App\Policies;

use App\Enums\DecisionStatus;
use App\Enums\Role;
use App\Models\DecisionFile;
use App\Models\User;

/**
 * Researchers get the signed letter and the consolidated comments of their
 * own application, only after release. Minutes extracts stay internal.
 */
class DecisionFilePolicy
{
    public function download(User $user, DecisionFile $file): bool
    {
        if ($user->hasRole(Role::Secretariat, Role::Chair)) {
            return true;
        }

        $decision = $file->decision;

        return $user->role === Role::Researcher
            && $decision->application->isOwnedBy($user)
            && $decision->isReleased()
            && $file->isReleasable();
    }

    public function delete(User $user, DecisionFile $file): bool
    {
        return $user->role === Role::Secretariat && $file->decision->status === DecisionStatus::Draft;
    }
}
