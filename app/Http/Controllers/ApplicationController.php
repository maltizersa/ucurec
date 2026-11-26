<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ApplicationService;
use App\Support\ApplicationFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Application::class);

        $user = $request->user();
        $filters = new ApplicationFilters($request);
        $isStaff = $user->can('exportRegister', Application::class);

        $applications = $filters->apply(Application::query()->visibleTo($user))
            ->with('latestStatusChange')
            ->when($isStaff, fn ($query) => $query->withExists([
                'reviewAssignments as has_overdue_review' => fn ($q) => $q->overdue(),
            ]))
            ->paginate(20)
            ->withQueryString();

        return view('applications.index', [
            'applications' => $applications,
            'filters' => $filters->values,
            'isFiltered' => $filters->isFiltered(),
            'exportQuery' => $filters->query(),
            'statuses' => ApplicationStatus::cases(),
            'isResearcher' => $user->can('create', Application::class),
            'isStaff' => $isStaff,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Application::class);

        $user = $request->user();
        $application = new Application([
            'principal_investigator' => $user->name,
            'pi_contact' => trim($user->email.' '.($user->contact_number ? '/ '.$user->contact_number : '')),
            'college_program' => $user->college_program,
        ]);

        return view('applications.create', $this->formData($application));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Application::class);

        $application = $this->service->createDraft($request->user(), $request->validate($this->rules()));

        return redirect()->route('applications.show', $application)
            ->with('success', 'Draft saved. Upload your documents, then submit the application.');
    }

    public function show(Request $request, Application $application): View
    {
        $this->authorize('view', $application);

        $application->load([
            'researcher:id,name,email',
            'documentSets' => fn ($query) => $query->whereNotNull('submitted_at')->with('documents', 'submitter:id,name'),
            'draftSet.documents',
            'screenings.screener:id,name',
            'screenings.documentSet:id,version',
            'statusHistories.user:id,name,role',
        ]);

        $user = $request->user();
        $isInternal = $user->can('viewInternal', $application);

        // Reviewer identities and reports are internal: never loaded for researchers.
        if ($isInternal) {
            $application->load([
                'reviewAssignments.reviewer:id,name',
                'reviewAssignments.documentSet:id,version',
                'reviewAssignments.files',
            ]);
        }

        $canCoordinate = $user->can('coordinateReview', $application);

        // Researchers only ever receive released decisions and their releasable files.
        $application->load(['decisions.files', 'decisions.documentSet:id,version']);
        $releasedDecisions = $application->decisions->filter(fn ($decision) => $decision->isReleased())->values();

        $requiredCategories = collect(config('ucurec.document_categories'))->filter(fn ($c) => $c['required'])->keys();
        if ($application->status === ApplicationStatus::ForRevision) {
            $requiredCategories->push('response_letter');
        }

        return view('applications.show', [
            'releasedDecisions' => $releasedDecisions,
            'openDecision' => $isInternal ? $application->decisions->first(fn ($d) => ! $d->isReleased()) : null,
            'requiredCategories' => $requiredCategories->all(),
            'application' => $application,
            'submittedSets' => $application->documentSets->sortByDesc('version'),
            'draft' => $application->draftSet,
            'latestScreening' => $application->screenings->first(),
            'categories' => config('ucurec.document_categories'),
            'uploadRules' => config('ucurec.uploads'),
            'canEdit' => $user->can('update', $application),
            'isInternal' => $isInternal,
            'canCoordinate' => $canCoordinate,
            'reviewers' => $canCoordinate
                ? User::role(Role::Reviewer)->active()->orderBy('name')->get(['id', 'name', 'college_program'])
                : collect(),
            'defaultDueDate' => today()->addDays(config('ucurec.review_days'))->toDateString(),
        ]);
    }

    public function edit(Application $application): View
    {
        $this->authorize('update', $application);

        return view('applications.edit', $this->formData($application));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $application->fill($request->validate($this->rules()));
        $changed = array_keys($application->getDirty());
        $application->save();

        if ($changed) {
            AuditLog::record('application.updated', $application, 'Updated the application record.', ['fields' => $changed]);
        }

        return redirect()->route('applications.show', $application)->with('success', 'Application details saved.');
    }

    /**
     * Deletes a draft that was never submitted, with its uploaded files.
     */
    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        DB::transaction(function () use ($application) {
            AuditLog::record('application.draft_deleted', $application, 'Deleted an unsubmitted draft.');
            $application->delete();
        });
        Storage::disk(ApplicationService::DISK)->deleteDirectory('applications/'.$application->id);

        return redirect()->route('applications.index')->with('success', 'Draft deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Application $application): array
    {
        return [
            'application' => $application,
            'colleges' => config('ucurec.colleges'),
            'researchTypes' => config('ucurec.research_types'),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'principal_investigator' => ['required', 'string', 'max:255'],
            'pi_contact' => ['required', 'string', 'max:255'],
            'adviser' => ['nullable', 'string', 'max:255'],
            'college_program' => ['required', 'string', Rule::in(config('ucurec.colleges'))],
            'research_type' => ['required', 'string', Rule::in(config('ucurec.research_types'))],
        ];
    }
}
