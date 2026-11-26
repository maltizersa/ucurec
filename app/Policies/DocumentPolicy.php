<?php

namespace App\Policies;

use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Whoever may view the application may download its documents.
 * Reviewers may download only the exact version they were assigned,
 * and only after accepting without a conflict of interest.
 */
class DocumentPolicy
{
    public function download(User $user, Document $document): bool
    {
        $set = $document->documentSet;
        $application = $set->application;

        // Staff never see a researcher's unsubmitted draft files.
        if ($set->isDraft() && ! $application->isOwnedBy($user)) {
            return false;
        }

        if ($user->role === Role::Reviewer) {
            return ReviewAssignment::where('reviewer_id', $user->id)
                ->where('document_set_id', $set->id)
                ->whereIn('status', [AssignmentStatus::Accepted->value, AssignmentStatus::Submitted->value])
                ->exists();
        }

        return Gate::forUser($user)->allows('view', $application);
    }

    public function delete(User $user, Document $document): bool
    {
        $set = $document->documentSet;

        return $set->isDraft()
            && Gate::forUser($user)->allows('update', $set->application);
    }
}
