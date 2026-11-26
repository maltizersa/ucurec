<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Secretariat and Chair actions on reviewer assignments.
 */
class ReviewAssignmentController extends Controller
{
    public function __construct(private ReviewService $service) {}

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('coordinateReview', $application);

        $validated = $request->validate([
            'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'due_at' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $this->service->propose(
            $application,
            $request->user(),
            User::findOrFail($validated['reviewer_id']),
            Carbon::parse($validated['due_at']),
        );

        return $this->backToReviewers($application, 'Reviewer proposed. The Chairperson must authorize the assignment.');
    }

    public function approve(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('approve', $assignment);

        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->service->approve($assignment, $request->user(), $validated['note'] ?? null);

        return $this->backToReviewers($assignment->application, 'Assignment authorized. The reviewer can now respond.');
    }

    public function reject(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('reject', $assignment);

        $validated = $request->validate(['note' => ['required', 'string', 'max:500']], [
            'note.required' => 'Give a reason so the Secretariat can propose someone else.',
        ]);
        $this->service->reject($assignment, $request->user(), $validated['note']);

        return $this->backToReviewers($assignment->application, 'Assignment not authorized.');
    }

    public function cancel(Request $request, ReviewAssignment $assignment): RedirectResponse
    {
        $this->authorize('cancel', $assignment);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->cancel($assignment, $request->user(), $validated['reason']);

        return $this->backToReviewers($assignment->application, 'Assignment cancelled.');
    }

    public function route(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('coordinateReview', $application);

        $validated = $request->validate([
            'review_route' => ['nullable', Rule::in(array_keys(config('ucurec.review_routes')))],
        ]);
        $this->service->setReviewRoute($application, $request->user(), $validated['review_route'] ?? null);

        return $this->backToReviewers($application, 'Review route saved.');
    }

    public function forward(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('coordinateReview', $application);

        $this->service->forwardForDecision($application, $request->user());

        return redirect()->route('applications.show', $application)
            ->with('success', 'Forwarded for the REC decision. Reviewer recommendations are advice only; they are not the decision.');
    }

    private function backToReviewers(Application $application, string $message): RedirectResponse
    {
        return redirect()->to(route('applications.show', $application).'#reviewers')->with('success', $message);
    }
}
