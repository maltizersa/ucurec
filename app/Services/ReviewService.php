<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\AssignmentStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\ReviewAssignment;
use App\Models\ReviewFile;
use App\Models\User;
use App\Support\SafeFilename;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reviewer coordination (Module 3). Controllers authorize the actor;
 * this class enforces the workflow rules.
 */
class ReviewService
{
    public function propose(Application $application, User $by, User $reviewer, CarbonInterface $dueAt): ReviewAssignment
    {
        if ($reviewer->role !== Role::Reviewer || ! $reviewer->is_active) {
            throw ValidationException::withMessages(['reviewer_id' => 'Choose an active reviewer account.']);
        }

        $previous = $application->reviewAssignments()->where('reviewer_id', $reviewer->id)->get();

        if ($previous->contains(fn (ReviewAssignment $a) => $a->status === AssignmentStatus::Conflicted)) {
            throw ValidationException::withMessages(['reviewer_id' => 'This reviewer declared a conflict of interest for this application.']);
        }

        if ($previous->contains(fn (ReviewAssignment $a) => $a->status->isOpen())) {
            throw ValidationException::withMessages(['reviewer_id' => 'This reviewer already has an open assignment for this application.']);
        }

        $set = $application->latestSubmittedSet()->firstOrFail();

        $assignment = $application->reviewAssignments()->create([
            'document_set_id' => $set->id,
            'reviewer_id' => $reviewer->id,
            'status' => AssignmentStatus::Proposed,
            'due_at' => $dueAt->toDateString(),
            'proposed_by' => $by->id,
        ]);

        AuditLog::record('assignment.proposed', $application, "Proposed a reviewer for version {$set->version}.", [
            'assignment_id' => $assignment->id,
            'reviewer_id' => $reviewer->id,
            'due_at' => $assignment->due_at->toDateString(),
        ], $by);

        return $assignment;
    }

    public function approve(ReviewAssignment $assignment, User $chair, ?string $note = null): void
    {
        $assignment->update([
            'status' => AssignmentStatus::Authorized,
            'authorized_by' => $chair->id,
            'authorized_at' => now(),
            'authorization_note' => $note,
        ]);

        AuditLog::record('assignment.authorized', $assignment->application, 'Authorized a reviewer assignment.', [
            'assignment_id' => $assignment->id,
        ], $chair);
    }

    public function reject(ReviewAssignment $assignment, User $chair, string $note): void
    {
        $assignment->update([
            'status' => AssignmentStatus::NotAuthorized,
            'authorized_by' => $chair->id,
            'authorized_at' => now(),
            'authorization_note' => $note,
        ]);

        AuditLog::record('assignment.not_authorized', $assignment->application, 'Did not authorize a reviewer assignment.', [
            'assignment_id' => $assignment->id,
        ], $chair);
    }

    public function cancel(ReviewAssignment $assignment, User $by, string $reason): void
    {
        $assignment->update([
            'status' => AssignmentStatus::Cancelled,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        AuditLog::record('assignment.cancelled', $assignment->application, 'Cancelled a reviewer assignment.', [
            'assignment_id' => $assignment->id,
        ], $by);
    }

    /**
     * The reviewer accepts or declines and declares any conflict of
     * interest. A declared conflict always blocks the assignment.
     */
    public function respond(ReviewAssignment $assignment, User $reviewer, bool $accept, bool $hasConflict, ?string $conflictDetails, ?string $declineReason): void
    {
        $status = match (true) {
            $hasConflict => AssignmentStatus::Conflicted,
            ! $accept => AssignmentStatus::Declined,
            default => AssignmentStatus::Accepted,
        };

        $assignment->update([
            'status' => $status,
            'responded_at' => now(),
            'has_conflict' => $hasConflict,
            'conflict_details' => $hasConflict ? $conflictDetails : null,
            'decline_reason' => $status === AssignmentStatus::Declined ? $declineReason : null,
        ]);

        // The conflict details stay in the assignment record, not in the log.
        $action = match ($status) {
            AssignmentStatus::Conflicted => 'assignment.conflict_declared',
            AssignmentStatus::Declined => 'assignment.declined',
            default => 'assignment.accepted',
        };

        AuditLog::record($action, $assignment->application, 'Reviewer responded: '.$status->label().'.', [
            'assignment_id' => $assignment->id,
            'has_conflict' => $hasConflict,
        ], $reviewer);
    }

    public function uploadReviewFile(ReviewAssignment $assignment, User $reviewer, string $formType, UploadedFile $file): ReviewFile
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs(
            'reviews/'.$assignment->id,
            Str::uuid()->toString().'.'.$extension,
            ApplicationService::DISK,
        );

        $reviewFile = $assignment->files()->create([
            'form_type' => $formType,
            'original_name' => SafeFilename::make($file->getClientOriginalName(), $extension),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $reviewer->id,
        ]);

        AuditLog::record('review_file.uploaded', $assignment->application, 'Uploaded a review form.', [
            'assignment_id' => $assignment->id,
            'review_file_id' => $reviewFile->id,
            'form_type' => $formType,
        ], $reviewer);

        return $reviewFile;
    }

    public function removeReviewFile(ReviewFile $file, User $reviewer): void
    {
        $assignment = $file->assignment;

        $file->delete();
        Storage::disk(ApplicationService::DISK)->delete($file->stored_path);

        AuditLog::record('review_file.removed', $assignment->application, 'Removed a review form before submitting.', [
            'assignment_id' => $assignment->id,
            'review_file_id' => $file->id,
        ], $reviewer);
    }

    public function submitReview(ReviewAssignment $assignment, User $reviewer, string $recommendation, ?string $comments): void
    {
        if (! $assignment->files()->exists()) {
            throw ValidationException::withMessages(['submit' => 'Upload your completed review form (Form 4A and/or 4B) before submitting.']);
        }

        $assignment->update([
            'status' => AssignmentStatus::Submitted,
            'recommendation' => $recommendation,
            'review_comments' => $comments,
            'submitted_at' => now(),
        ]);

        // The recommendation itself is internal and is not written to the log.
        AuditLog::record('review.submitted', $assignment->application, 'Submitted a review.', [
            'assignment_id' => $assignment->id,
        ], $reviewer);
    }

    /**
     * Ends the review stage. Needs at least one submitted review of the
     * current version (none for an exempt route) and no open assignments.
     * Recommendations are passed to the Committee; they are not a decision.
     */
    public function forwardForDecision(Application $application, User $by): void
    {
        DB::transaction(function () use ($application, $by) {
            $set = $application->latestSubmittedSet()->firstOrFail();
            $assignments = $application->reviewAssignments()->where('document_set_id', $set->id)->get();

            if ($assignments->contains(fn (ReviewAssignment $a) => $a->status->isOpen())) {
                throw ValidationException::withMessages(['forward' => 'Some reviewer assignments are still open. Wait for them or cancel them first.']);
            }

            $submitted = $assignments->where('status', AssignmentStatus::Submitted)->count();
            if ($submitted === 0 && $application->review_route !== 'exempt') {
                throw ValidationException::withMessages(['forward' => 'At least one submitted review is needed (unless the review route is Exempt).']);
            }

            $application->transitionTo(
                ApplicationStatus::AwaitingDecision,
                $by,
                "Review stage closed with {$submitted} submitted review(s).",
            );
        });
    }

    public function setReviewRoute(Application $application, User $by, ?string $route): void
    {
        $application->review_route = $route;
        $application->save();

        AuditLog::record('application.route_set', $application, 'Recorded the review route.', ['route' => $route], $by);
    }
}
