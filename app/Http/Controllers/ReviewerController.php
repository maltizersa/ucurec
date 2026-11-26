<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Models\ReviewAssignment;
use App\Models\ReviewFile;
use App\Services\ReviewService;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The reviewer's side: invitations, conflict declaration, and the review.
 */
class ReviewerController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function index(Request $request): View
    {
        $assignments = ReviewAssignment::query()
            ->where('reviewer_id', $request->user()->id)
            ->whereNotIn('status', [AssignmentStatus::Proposed->value, AssignmentStatus::NotAuthorized->value])
            ->with('application:id,reference_no,title,research_type', 'documentSet:id,version')
            ->orderByRaw("CASE status WHEN 'authorized' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->paginate(15);

        return view('reviews.index', ['assignments' => $assignments]);
    }

    public function show(ReviewAssignment $assignment): View
    {
        $this->authorize('view', $assignment);

        $assignment->load(['application', 'documentSet.documents', 'files']);

        return view('reviews.show', [
            'assignment' => $assignment,
            'application' => $assignment->application,
            'canSeeFiles' => $assignment->status->grantsFileAccess(),
            'recommendations' => config('ucurec.review_recommendations'),
            'formTypes' => config('ucurec.review_form_types'),
            'uploadRules' => config('ucurec.uploads'),
        ]);
    }

    public function respond(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('respond', $assignment);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'decline'])],
            'has_conflict' => ['required', Rule::in(['yes', 'no'])],
            'conflict_details' => ['required_if:has_conflict,yes', 'nullable', 'string', 'max:2000'],
            'decline_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'has_conflict.required' => 'Declare whether you have a conflict of interest.',
            'conflict_details.required_if' => 'Briefly describe the conflict of interest.',
        ]);

        $hasConflict = $validated['has_conflict'] === 'yes';

        $this->service->respond(
            $assignment,
            $request->user(),
            $validated['decision'] === 'accept',
            $hasConflict,
            $validated['conflict_details'] ?? null,
            $validated['decline_reason'] ?? null,
        );

        $message = match ($assignment->status) {
            AssignmentStatus::Accepted => 'Thank you. You can now open the assigned documents.',
            AssignmentStatus::Conflicted => 'Conflict of interest recorded. You will not have access to this application; the Secretariat will reassign it.',
            default => 'You declined this assignment. The Secretariat will reassign it.',
        };

        return redirect()->route('reviews.show', $assignment)->with('success', $message);
    }

    public function uploadFile(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('review', $assignment);

        $validated = $request->validate([
            'form_type' => ['required', Rule::in(array_keys(config('ucurec.review_form_types')))],
            'file' => UploadRules::rules(),
        ], UploadRules::messages());

        $this->service->uploadReviewFile($assignment, $request->user(), $validated['form_type'], $validated['file']);

        return redirect()->to(route('reviews.show', $assignment).'#review')->with('success', 'Review form uploaded.');
    }

    public function removeFile(Request $request, ReviewAssignment $assignment, ReviewFile $file): RedirectResponse
    {
        abort_unless($file->review_assignment_id === $assignment->id, 404);
        $this->authorize('delete', $file);

        $this->service->removeReviewFile($file, $request->user());

        return redirect()->to(route('reviews.show', $assignment).'#review')->with('success', 'Review form removed.');
    }

    public function submit(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('review', $assignment);

        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(array_keys(config('ucurec.review_recommendations')))],
            'review_comments' => ['nullable', 'string', 'max:3000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Please confirm that your review is final.',
        ]);

        $this->service->submitReview($assignment, $request->user(), $validated['recommendation'], $validated['review_comments'] ?? null);

        return redirect()->route('reviews.show', $assignment)->with('success', 'Review submitted. Thank you.');
    }
}
