<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Main administrator: manages every account, including other administrators.
 *   No one else can edit, deactivate, or reset the main administrator.
 * IT administrator: manages every account except administrator accounts.
 * REC Secretariat: activates and deactivates researcher accounts only.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::Admin, Role::Secretariat);
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Only the main administrator may give an account the administrator role.
     */
    public function assignAdminRole(User $actor): bool
    {
        return $actor->isMainAdmin();
    }

    /**
     * Administrators may always edit their own details (but not their own
     * role; UserController blocks that).
     */
    public function update(User $actor, User $target): bool
    {
        if ($actor->isAdmin() && $actor->is($target)) {
            return true;
        }

        return ! $target->isMainAdmin() && $this->adminCanManage($actor, $target);
    }

    public function activate(User $actor, User $target): bool
    {
        return ! $target->is_active && $this->canManageStatus($actor, $target);
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $target->is_active
            && $actor->isNot($target)
            && ! $target->isMainAdmin()
            && $this->canManageStatus($actor, $target);
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->isNot($target)
            && ! $target->isMainAdmin()
            && $this->adminCanManage($actor, $target);
    }

    private function canManageStatus(User $actor, User $target): bool
    {
        return $this->adminCanManage($actor, $target)
            || ($actor->role === Role::Secretariat && $target->role === Role::Researcher);
    }

    /**
     * Administrators manage non-admin accounts; administrator accounts are
     * managed by the main administrator only.
     */
    private function adminCanManage(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        return $target->role !== Role::Admin || $actor->isMainAdmin();
    }
}
