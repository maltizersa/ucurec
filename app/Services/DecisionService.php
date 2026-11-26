<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Decision;
use App\Models\DecisionFile;
use App\Models\User;
use App\Support\SafeFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Decisions and letters (Module 5) and the release of consolidated
 * comments for revision (Module 4).
 */
class DecisionService
{
    /**
     * @param  array{category: string, decision_date: string, approval_date?: ?string, expiry_date?: ?string, basis?: ?string, consolidated_comments?: ?string}  $data
     */
    public function create(Application $application, User $by, array $data): Decision
    {
        $set = $application->latestSubmittedSet()->firstOrFail();

        $decision = $application->decisions()->create([
            ...$this->fields($data),
            'document_set_id' => $set->id,
            'status' => DecisionStatus::Draft,
            'recorded_by' => $by->id,
        ]);

        AuditLog::record('decision.created', $application, "Started recording the REC decision for version {$set->version}.", [
            'decision_id' => $decision->id,
        ], $by);

        return $decision;
    }

    public function update(Decision $decision, User $by, array $data): void
    {
        $decision->update($this->fields($data));

        AuditLog::record('decision.updated', $decision->application, 'Updated the draft REC decision.', [
            'decision_id' => $decision->id,
        ], $by);
    }

    public function uploadFile(Decision $decision, User $by, string $kind, UploadedFile $file): DecisionFile
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs(
            'decisions/'.$decision->id,
            Str::uuid()->toString().'.'.$extension,
            ApplicationService::DISK,
        );

        $decisionFile = $decision->files()->create([
            'kind' => $kind,
            'original_name' => SafeFilename::make($file->getClientOriginalName(), $extension),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $by->id,
        ]);

        AuditLog::record('decision_file.uploaded', $decision->application, 'Uploaded a decision file.', [
            'decision_id' => $decision->id,
            'decision_file_id' => $decisionFile->id,
            'kind' => $kind,
        ], $by);

        return $decisionFile;
    }

    public function removeFile(DecisionFile $file, User $by): void
    {
        $decision = $file->decision;

        $file->delete();
        Storage::disk(ApplicationService::DISK)->delete($file->stored_path);

        AuditLog::record('decision_file.removed', $decision->application, 'Removed a file from the draft decision.', [
            'decision_id' => $decision->id,
            'decision_file_id' => $file->id,
        ], $by);
    }

    public function sendForConfirmation(Decision $decision, User $by): void
    {
        $this->ensureComplete($decision);

        $decision->update([
            'status' => DecisionStatus::AwaitingConfirmation,
            'sent_for_confirmation_at' => now(),
            'chair_note' => null,
        ]);

        AuditLog::record('decision.sent_for_confirmation', $decision->application, 'Sent the REC decision to the Chairperson.', [
            'decision_id' => $decision->id,
        ], $by);
    }

    public function sendBack(Decision $decision, User $chair, string $note): void
    {
        $decision->update([
            'status' => DecisionStatus::Draft,
            'chair_note' => $note,
        ]);

        AuditLog::record('decision.returned', $decision->application, 'Returned the decision to the Secretariat.', [
            'decision_id' => $decision->id,
        ], $chair);
    }

    /**
     * The Chairperson confirms; the decision and its releasable files become
     * visible to the researcher, and the application moves on.
     */
    public function confirmAndRelease(Decision $decision, User $chair): void
    {
        $this->ensureComplete($decision);

        DB::transaction(function () use ($decision, $chair) {
            $now = now();
            $decision->update([
                'status' => DecisionStatus::Released,
                'confirmed_by' => $chair->id,
                'confirmed_at' => $now,
                'released_at' => $now,
            ]);

            $application = $decision->application;
            $label = $decision->categoryLabel();

            if ($decision->requiresRevision()) {
                $application->transitionTo(ApplicationStatus::ForRevision, $chair, "REC decision released: {$label}. Revisions requested.");
            } else {
                $application->transitionTo(ApplicationStatus::DecisionReleased, $chair, "REC decision released: {$label}.");
            }

            AuditLog::record('decision.released', $application, 'Confirmed and released the REC decision.', [
                'decision_id' => $decision->id,
                'category' => $decision->category,
            ], $chair);
        });
    }

    /**
     * What a decision needs before the Chair can release it.
     */
    private function ensureComplete(Decision $decision): void
    {
        $decision->loadMissing('files', 'application');
        $kinds = $decision->files->pluck('kind');
        $errors = [];

        if (! $kinds->contains(Decision::FILE_LETTER)) {
            $errors[] = 'Upload the signed official letter or clearance.';
        }

        if ($decision->application->review_route === 'full' && ! $kinds->contains(Decision::FILE_MINUTES)) {
            $errors[] = 'Full committee review: attach the decision record or minutes extract.';
        }

        if ($decision->isApproval() && ! $decision->approval_date) {
            $errors[] = 'Enter the approval date.';
        }

        if ($decision->requiresRevision() && blank($decision->consolidated_comments) && ! $kinds->contains(Decision::FILE_COMMENTS)) {
            $errors[] = 'Add the consolidated comments for the researcher (text or document).';
        }

        if ($errors) {
            throw ValidationException::withMessages(['decision' => $errors]);
        }
    }

    private function fields(array $data): array
    {
        return [
            'category' => $data['category'],
            'decision_date' => $data['decision_date'],
            'approval_date' => $data['approval_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'basis' => $data['basis'] ?? null,
            'consolidated_comments' => $data['consolidated_comments'] ?? null,
        ];
    }
}
