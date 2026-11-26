<?php

namespace App\Policies;

use App\Enums\DecisionStatus;
use App\Enums\Role;
use App\Models\Decision;
use App\Models\User;

/**
 * The Secretariat records the decision; the Chairperson confirms its
 * release or sends it back. Researchers see released decisions on their
 * application page only.
 */
class DecisionPolicy
{
    public function view(User $user, Decision $decision): bool
    {
        return $user->hasRole(Role::Secretariat, Role::Chair);
    }

    public function update(User $user, Decision $decision): bool
    {
        return $user->role === Role::Secretariat && $decision->status === DecisionStatus::Draft;
    }

    public function confirm(User $user, Decision $decision): bool
    {
        return $user->role === Role::Chair && $decision->status === DecisionStatus::AwaitingConfirmation;
    }

    public function sendBack(User $user, Decision $decision): bool
    {
        return $this->confirm($user, $decision);
    }
}
