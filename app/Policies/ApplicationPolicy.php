<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\DocumentSet;
use App\Models\User;

/**
 * Researchers see only their own applications. The Secretariat and the
 * Chair see submitted applications. Reviewers get access per assignment
 * (Phase 2). The IT administrator has no access to applications.
 */
class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Researcher, Role::Secretariat, Role::Chair);
    }

    public function view(User $user, Application $application): bool
    {
        if ($application->isOwnedBy($user)) {
            return true;
        }

        return $user->hasRole(Role::Secretariat, Role::Chair)
            && $application->status !== ApplicationStatus::Draft;
    }

    /**
     * The CSV application register and the staff search filters.
     */
    public function exportRegister(User $user): bool
    {
        return $user->hasRole(Role::Secretariat, Role::Chair);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Researcher;
    }

    /**
     * Edit details, upload or remove files, and submit.
     */
    public function update(User $user, Application $application): bool
    {
        return $application->isOwnedBy($user)
            && $application->status->isEditableByResearcher();
    }

    /**
     * Only a draft that was never submitted can be deleted.
     */
    public function delete(User $user, Application $application): bool
    {
        return $application->isOwnedBy($user)
            && $application->status === ApplicationStatus::Draft
            && $application->reference_no === null;
    }

    public function startScreening(User $user, Application $application): bool
    {
        return $user->role === Role::Secretariat
            && $application->status === ApplicationStatus::Submitted;
    }

    public function screen(User $user, Application $application): bool
    {
        return $user->role === Role::Secretariat
            && $application->status === ApplicationStatus::ForScreening;
    }

    /**
     * Propose reviewers, cancel assignments, set the review route, and
     * forward the application for the REC decision.
     */
    public function coordinateReview(User $user, Application $application): bool
    {
        return $user->role === Role::Secretariat
            && $application->status === ApplicationStatus::ForReview;
    }

    /**
     * Start recording the REC decision (one open decision at a time).
     */
    public function recordDecision(User $user, Application $application): bool
    {
        return $user->role === Role::Secretariat
            && $application->status === ApplicationStatus::AwaitingDecision
            && ! $application->openDecision()->exists();
    }

    /**
     * Send a resubmitted revised version to screening, review, or decision.
     */
    public function routeRevision(User $user, Application $application): bool
    {
        return $user->role === Role::Secretariat
            && $application->status === ApplicationStatus::Submitted
            && $application->latestSubmittedSet()->first()?->kind === DocumentSet::KIND_REVISION;
    }

    /**
     * Internal records: reviewers and their reports, status history with
     * staff names, full screening details. Researchers never see these.
     */
    public function viewInternal(User $user, Application $application): bool
    {
        return $user->hasRole(Role::Secretariat, Role::Chair);
    }
}
