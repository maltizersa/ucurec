<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Models\Application;
use App\Models\Decision;
use App\Models\DecisionFile;
use App\Services\DecisionService;
use App\Support\UploadRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Secretariat records the REC decision; the Chairperson confirms and
 * releases it, or sends it back.
 */
class DecisionController extends Controller
{
    public function __construct(private DecisionService $service) {}

    public function create(Application $application): View
    {
        $this->authorize('recordDecision', $application);

        return view('decisions.create', [
            'application' => $application,
            'decision' => new Decision(['decision_date' => today()]),
            ...$this->context($application),
        ]);
    }

    public function store(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('recordDecision', $application);

        $decision = $this->service->create($application, $request->user(), $this->validated($request));

        return redirect()->route('decisions.show', $decision)
            ->with('success', 'Draft decision saved. Upload the signed letter, then send it to the Chairperson.');
    }

    public function show(Decision $decision): View
    {
        $this->authorize('view', $decision);

        $decision->load(['files', 'recorder:id,name', 'confirmer:id,name', 'documentSet.documents']);
        $application = $decision->application;

        return view('decisions.show', [
            'decision' => $decision,
            'application' => $application,
            ...$this->context($application, $decision),
        ]);
    }

    public function update(Request $request, Decision $decision): RedirectResponse
    {
        $this->authorize('update', $decision);

        $this->service->update($decision, $request->user(), $this->validated($request));

        return redirect()->route('decisions.show', $decision)->with('success', 'Draft decision saved.');
    }

    public function uploadFile(Request $request, Decision $decision): RedirectResponse
    {
        $this->authorize('update', $decision);

        $validated = $request->validate([
            'kind' => ['required', Rule::in([Decision::FILE_LETTER, Decision::FILE_COMMENTS, Decision::FILE_MINUTES])],
            'file' => UploadRules::rules(),
        ], UploadRules::messages());

        $this->service->uploadFile($decision, $request->user(), $validated['kind'], $validated['file']);

        return redirect()->to(route('decisions.show', $decision).'#files')->with('success', 'File uploaded.');
    }

    public function removeFile(Request $request, Decision $decision, DecisionFile $file): RedirectResponse
    {
        abort_unless($file->decision_id === $decision->id, 404);
        $this->authorize('delete', $file);

        $this->service->removeFile($file, $request->user());

        return redirect()->to(route('decisions.show', $decision).'#files')->with('success', 'File removed.');
    }

    public function sendForConfirmation(Request $request, Decision $decision): RedirectResponse
    {
        $this->authorize('update', $decision);

        $this->service->sendForConfirmation($decision, $request->user());

        return redirect()->route('decisions.show', $decision)->with('success', 'Sent to the Chairperson for confirmation.');
    }

    public function confirm(Request $request, Decision $decision): RedirectResponse
    {
        $this->authorize('confirm', $decision);

        $this->service->confirmAndRelease($decision, $request->user());

        return redirect()->route('applications.show', $decision->application)
            ->with('success', 'Decision confirmed and released to the researcher.');
    }

    public function sendBack(Request $request, Decision $decision): RedirectResponse
    {
        $this->authorize('sendBack', $decision);

        $validated = $request->validate(['chair_note' => ['required', 'string', 'max:1000']], [
            'chair_note.required' => 'Tell the Secretariat what to change.',
        ]);
        $this->service->sendBack($decision, $request->user(), $validated['chair_note']);

        return redirect()->route('decisions.show', $decision)->with('success', 'Returned to the Secretariat.');
    }

    /**
     * Reviewer recommendations for the version being decided: shown as
     * advice next to the form, never copied into the decision.
     */
    private function context(Application $application, ?Decision $decision = null): array
    {
        $setId = $decision?->document_set_id ?? $application->latestSubmittedSet()->value('id');

        return [
            'categories' => config('ucurec.decision_categories'),
            'recommendations' => $application->reviewAssignments()
                ->where('document_set_id', $setId)
                ->where('status', AssignmentStatus::Submitted->value)
                ->with('reviewer:id,name', 'files')
                ->get(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(array_keys(config('ucurec.decision_categories')))],
            'decision_date' => ['required', 'date', 'before_or_equal:today'],
            'approval_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:approval_date'],
            'basis' => ['nullable', 'string', 'max:2000'],
            'consolidated_comments' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
