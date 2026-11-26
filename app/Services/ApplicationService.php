<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentSet;
use App\Models\Screening;
use App\Models\User;
use App\Support\SafeFilename;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Researcher and Secretariat actions on an application (Module 2).
 * Controllers authorize; this class enforces the workflow rules.
 */
class ApplicationService
{
    public const DISK = 'documents';

    public function __construct(private ReferenceNumberGenerator $references) {}

    public function createDraft(User $researcher, array $data): Application
    {
        return DB::transaction(function () use ($researcher, $data) {
            $application = new Application($data);
            $application->researcher_id = $researcher->id;
            $application->transitionTo(ApplicationStatus::Draft, $researcher, 'Application started.');

            $application->documentSets()->create([
                'version' => 1,
                'kind' => DocumentSet::KIND_INITIAL,
                'created_by' => $researcher->id,
            ]);

            AuditLog::record('application.created', $application, 'Started an application.', user: $researcher);

            return $application;
        });
    }

    /**
     * The editable set for the next submission. After a submission, a new
     * draft is created that starts with the files of the last submitted set
     * (same stored files, nothing is copied or overwritten).
     */
    public function draftSet(Application $application, User $by): DocumentSet
    {
        if ($draft = $application->draftSet()->first()) {
            return $draft;
        }

        return DB::transaction(function () use ($application, $by) {
            $previous = $application->latestSubmittedSet()->with('documents')->firstOrFail();

            $draft = $application->documentSets()->create([
                'version' => $previous->version + 1,
                'kind' => $application->status === ApplicationStatus::ForRevision
                    ? DocumentSet::KIND_REVISION
                    : DocumentSet::KIND_RESUBMISSION,
                'created_by' => $by->id,
            ]);

            foreach ($previous->documents as $document) {
                $draft->documents()->create([
                    ...$document->only(['category', 'original_name', 'stored_path', 'mime_type', 'size', 'sha256', 'uploaded_by']),
                    'carried_from_id' => $document->id,
                    'created_at' => $document->created_at, // keep the original upload time
                ]);
            }

            return $draft;
        });
    }

    public function uploadDocument(Application $application, User $by, string $category, UploadedFile $file): Document
    {
        $draft = $this->draftSet($application, $by);

        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs(
            'applications/'.$application->id,
            Str::uuid()->toString().'.'.$extension,
            self::DISK,
        );

        $document = $draft->documents()->create([
            'category' => $category,
            'original_name' => SafeFilename::make($file->getClientOriginalName(), $extension),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $by->id,
        ]);

        AuditLog::record('document.uploaded', $application, "Uploaded a file to version {$draft->version}.", [
            'document_id' => $document->id,
            'category' => $category,
            'size' => $document->size,
        ], $by);

        return $document;
    }

    /**
     * Remove a file from the draft set. The stored file is deleted only when
     * it was uploaded into this draft and no other record points to it.
     */
    public function removeDocument(Document $document, User $by): void
    {
        $set = $document->documentSet;

        if (! $set->isDraft()) {
            throw ValidationException::withMessages(['document' => 'Files in a submitted version cannot be removed.']);
        }

        $document->delete();

        $stillUsed = Document::where('stored_path', $document->stored_path)->exists();
        if (! $stillUsed && $document->carried_from_id === null) {
            Storage::disk(self::DISK)->delete($document->stored_path);
        }

        AuditLog::record('document.removed', $set->application, "Removed a file from draft version {$set->version}.", [
            'document_id' => $document->id,
            'category' => $document->category,
        ], $by);
    }

    public function submit(Application $application, User $by, ?string $note = null): void
    {
        DB::transaction(function () use ($application, $by, $note) {
            $application->refresh();

            // A resubmission with no changes still becomes its own version.
            $draft = $application->draftSet()->first() ?? $this->draftSet($application, $by);
            $draft->load('documents');

            if ($draft->documents->isEmpty()) {
                throw ValidationException::withMessages(['submit' => 'Upload your documents before submitting.']);
            }

            $required = collect(config('ucurec.document_categories'))
                ->filter(fn (array $category) => $category['required'])
                ->keys();

            // A revision must come with the researcher's response to the REC comments.
            if ($application->status === ApplicationStatus::ForRevision) {
                $required->push('response_letter');
            }

            $missing = $required->diff($draft->categories());

            if ($missing->isNotEmpty()) {
                $labels = $missing->map(fn ($key) => config("ucurec.document_categories.{$key}.label"))->implode(', ');
                throw ValidationException::withMessages(['submit' => "Please upload: {$labels}."]);
            }

            $now = now();
            $draft->forceFill([
                'note' => $note ?: $draft->note,
                'submitted_by' => $by->id,
                'submitted_at' => $now,
            ])->save();

            $isFirstSubmission = $application->reference_no === null;
            if ($isFirstSubmission) {
                $application->reference_no = $this->references->next();
                $application->submitted_at = $now;
            }
            $application->last_submitted_at = $now;

            $application->transitionTo(
                ApplicationStatus::Submitted,
                $by,
                $isFirstSubmission ? 'Application submitted.' : "Version {$draft->version} submitted.",
            );

            AuditLog::record('application.submitted', $application, "Submitted version {$draft->version}.", [
                'reference_no' => $application->reference_no,
                'document_set_id' => $draft->id,
            ], $by);
        });
    }

    /**
     * Send a resubmitted revised version where UCUREC requires it:
     * back to screening, to review, or straight to the REC decision.
     */
    public function routeRevision(Application $application, User $by, string $target): void
    {
        [$status, $note] = match ($target) {
            'screening' => [ApplicationStatus::ForScreening, 'Revised version sent to screening.'],
            'review' => [ApplicationStatus::ForReview, 'Revised version sent for further review.'],
            'decision' => [ApplicationStatus::AwaitingDecision, 'Revised version sent for the REC decision.'],
        };

        $application->transitionTo($status, $by, $note);
    }

    public function startScreening(Application $application, User $by): void
    {
        $application->transitionTo(ApplicationStatus::ForScreening, $by, 'Screening started.');
    }

    /**
     * @param  array<string, array{state: string, note?: ?string}>  $items  keyed by checklist key
     */
    public function recordScreening(Application $application, User $by, array $items, ?string $remarks, ?string $reviewRoute): Screening
    {
        return DB::transaction(function () use ($application, $by, $items, $remarks, $reviewRoute) {
            $set = $application->latestSubmittedSet()->firstOrFail();

            $checklist = [];
            foreach (config('ucurec.screening_checklist') as $key => $label) {
                $checklist[] = [
                    'key' => $key,
                    'label' => $label,
                    'state' => $items[$key]['state'],
                    'note' => $items[$key]['note'] ?? null,
                ];
            }

            $hasMissing = collect($checklist)->contains('state', Screening::STATE_MISSING);
            $result = $hasMissing ? Screening::RESULT_INCOMPLETE : Screening::RESULT_COMPLETE;

            $screening = $application->screenings()->create([
                'document_set_id' => $set->id,
                'screened_by' => $by->id,
                'result' => $result,
                'checklist' => $checklist,
                'remarks' => $remarks,
            ]);

            if ($result === Screening::RESULT_COMPLETE) {
                $application->review_route = $reviewRoute;
                $application->transitionTo(ApplicationStatus::ForReview, $by, 'Screening complete; ready for reviewer assignment.');
            } else {
                $application->transitionTo(ApplicationStatus::Incomplete, $by, 'Returned to the researcher: missing requirements.');
            }

            AuditLog::record('screening.recorded', $application, "Screening result: {$result}.", [
                'screening_id' => $screening->id,
                'document_set_id' => $set->id,
                'missing' => collect($checklist)->where('state', Screening::STATE_MISSING)->pluck('key')->all(),
            ], $by);

            return $screening;
        });
    }

}
