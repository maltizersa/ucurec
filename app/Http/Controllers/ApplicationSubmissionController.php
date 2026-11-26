<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\ApplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationSubmissionController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Please confirm that the uploaded files are complete and final for this submission.',
        ]);

        $this->service->submit($application, $request->user(), $validated['note'] ?? null);

        return redirect()->route('applications.show', $application)
            ->with('success', "Submitted. Your reference number is {$application->reference_no}.");
    }
}
