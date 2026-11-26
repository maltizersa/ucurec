<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Screening;
use App\Services\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Secretariat completeness check against the fixed UCUREC checklist.
 */
class ScreeningController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function start(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('startScreening', $application);

        $this->service->startScreening($application, $request->user());

        return redirect()->route('applications.screening.create', $application);
    }

    public function create(Application $application): View
    {
        $this->authorize('screen', $application);

        $application->load(['latestSubmittedSet.documents', 'screenings.screener:id,name']);

        return view('applications.screening', [
            'application' => $application,
            'set' => $application->latestSubmittedSet,
            'checklist' => config('ucurec.screening_checklist'),
            'reviewRoutes' => config('ucurec.review_routes'),
            'previous' => $application->screenings->first(),
        ]);
    }

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('screen', $application);

        $states = [Screening::STATE_PRESENT, Screening::STATE_MISSING, Screening::STATE_NOT_APPLICABLE];
        $rules = [
            'remarks' => ['nullable', 'string', 'max:2000'],
            'review_route' => ['nullable', Rule::in(array_keys(config('ucurec.review_routes')))],
        ];
        foreach (array_keys(config('ucurec.screening_checklist')) as $key) {
            $rules["items.{$key}.state"] = ['required', Rule::in($states)];
            $rules["items.{$key}.note"] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules, [
            'items.*.state.required' => 'Mark every checklist item.',
        ]);

        $screening = $this->service->recordScreening(
            $application,
            $request->user(),
            $validated['items'],
            $validated['remarks'] ?? null,
            $validated['review_route'] ?? null,
        );

        $message = $screening->isComplete()
            ? 'Screening complete. The application is now For Review.'
            : 'Returned to the researcher with the list of missing requirements.';

        return redirect()->route('applications.show', $application)->with('success', $message);
    }
}
