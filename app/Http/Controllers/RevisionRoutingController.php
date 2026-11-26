<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Secretariat sends a resubmitted revision to screening, review, or
 * the REC decision. A revision is never a new application.
 */
class RevisionRoutingController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('routeRevision', $application);

        $validated = $request->validate(['target' => ['required', Rule::in(['screening', 'review', 'decision'])]]);
        $this->service->routeRevision($application, $request->user(), $validated['target']);

        if ($validated['target'] === 'screening') {
            return redirect()->route('applications.screening.create', $application);
        }

        return redirect()->route('applications.show', $application)->with('success', match ($validated['target']) {
            'review' => 'Revised version sent for further review. Propose reviewers for it below.',
            default => 'Revised version sent for the REC decision.',
        });
    }
}
