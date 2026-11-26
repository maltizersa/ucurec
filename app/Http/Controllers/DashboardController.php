<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\AssignmentStatus;
use App\Enums\DecisionStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\Decision;
use App\Models\DocumentSet;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\BackupService;
use App\Support\CollegeCharts;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Role-specific landing page: counts and the work that needs action.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, BackupService $backups, CollegeCharts $collegeCharts): View
    {
        $user = $request->user();
        $tiles = [];
        $items = collect();

        match ($user->role) {
            Role::Researcher => [$tiles, $items] = $this->researcher($user),
            Role::Secretariat => [$tiles, $items] = $this->secretariat(),
            Role::Chair => [$tiles, $items] = $this->chair(),
            Role::Reviewer => [$tiles, $items] = $this->reviewer($user),
            Role::Admin => null,
        };

        if ($user->hasRole(Role::Admin, Role::Secretariat)) {
            $tiles[] = $this->tile('Researcher accounts waiting for activation',
                User::role(Role::Researcher)->pendingActivation()->count(),
                route('users.index', ['status' => 'pending']));
        }

        if ($user->isAdmin()) {
            $tiles[] = $this->tile('Active accounts', User::active()->count(), route('users.index', ['status' => 'active']));
            $tiles[] = $this->tile('Deactivated accounts', User::where('is_active', false)->whereNotNull('activated_at')->count(),
                route('users.index', ['status' => 'deactivated']));
        }

        // Pie charts by college / program. The IT administrator sees account
        // counts only: application figures are for the REC (Secretariat, Chair).
        $charts = [];
        if ($user->hasRole(Role::Admin, Role::Secretariat, Role::Chair)) {
            $charts[] = $collegeCharts->researcherAccounts();
        }
        if ($user->hasRole(Role::Secretariat, Role::Chair)) {
            $charts[] = $collegeCharts->submittedApplications();
            $charts[] = $collegeCharts->passedApplications();
        }

        return view('dashboard', [
            'user' => $user,
            'tiles' => $tiles,
            'charts' => $charts,
            'actionItems' => $items,
            // Backup status only; backups themselves are handled on the server.
            'backup' => $user->isAdmin() ? ['latest' => $backups->latest()] : null,
        ]);
    }

    private function researcher(User $user): array
    {
        $needsAction = [ApplicationStatus::Draft->value, ApplicationStatus::Incomplete->value, ApplicationStatus::ForRevision->value];
        $mine = Application::where('researcher_id', $user->id);

        $tiles = [
            $this->tile('My applications', (clone $mine)->count(), route('applications.index')),
            $this->tile('Need your action', (clone $mine)->whereIn('status', $needsAction)->count(), route('applications.index')),
        ];

        $items = (clone $mine)->whereIn('status', $needsAction)->with('latestStatusChange')->latest('updated_at')->limit(10)->get()
            ->map(fn (Application $a) => $this->applicationItem($a));

        return [$tiles, $items];
    }

    private function secretariat(): array
    {
        $tiles = [];
        foreach ([ApplicationStatus::Submitted, ApplicationStatus::ForScreening, ApplicationStatus::Incomplete, ApplicationStatus::ForReview, ApplicationStatus::AwaitingDecision, ApplicationStatus::ForRevision] as $status) {
            $tiles[] = $this->tile($status->label(), Application::where('status', $status->value)->count(),
                route('applications.index', ['status' => $status->value]));
        }
        $tiles[] = $this->tile('Overdue reviews', ReviewAssignment::overdue()->count(),
            route('applications.index', ['status' => ApplicationStatus::ForReview->value]), danger: true);

        $toScreen = Application::whereIn('status', [ApplicationStatus::Submitted->value, ApplicationStatus::ForScreening->value])
            ->with('latestSubmittedSet', 'latestStatusChange')
            ->oldest('last_submitted_at')->limit(10)->get()
            ->map(fn (Application $a) => $this->applicationItem($a,
                $a->status === ApplicationStatus::Submitted && $a->latestSubmittedSet?->kind === DocumentSet::KIND_REVISION
                    ? 'Route revised version'
                    : 'Screen'));

        // Decisions to record, and drafts the Chair sent back.
        $toDecide = Application::where('status', ApplicationStatus::AwaitingDecision->value)
            ->whereDoesntHave('decisions', fn ($q) => $q->where('status', '!=', DecisionStatus::Released->value))
            ->with('latestStatusChange')
            ->limit(10)->get()
            ->map(fn (Application $a) => $this->applicationItem($a, 'Record REC decision'));

        $returned = Decision::where('status', DecisionStatus::Draft->value)->whereNotNull('chair_note')
            ->with('application:id,reference_no,title')->limit(10)->get()
            ->map(fn (Decision $d) => [
                'url' => route('decisions.show', $d),
                'reference' => $d->application->reference_no,
                'title' => $d->application->title,
                'badge' => 'Decision returned by the Chair',
                'badgeClass' => 'text-bg-warning',
            ]);

        // For Review with every assignment closed and at least one review in: ready to forward.
        $ready = Application::where('status', ApplicationStatus::ForReview->value)
            ->whereHas('reviewAssignments', fn ($q) => $q->where('status', AssignmentStatus::Submitted->value))
            ->whereDoesntHave('reviewAssignments', fn ($q) => $q->open())
            ->with('latestStatusChange')
            ->limit(10)->get()
            ->map(fn (Application $a) => $this->applicationItem($a, 'Ready to forward for decision'));

        $overdue = ReviewAssignment::overdue()->with('application:id,reference_no,title')->limit(10)->get()
            ->map(fn (ReviewAssignment $r) => [
                'url' => route('applications.show', $r->application).'#reviewers',
                'reference' => $r->application->reference_no,
                'title' => $r->application->title,
                'badge' => 'Review overdue since '.$r->due_at->format('M j'),
                'badgeClass' => 'text-bg-danger',
            ]);

        return [$tiles, $returned->concat($overdue)->concat($toDecide)->concat($ready)->concat($toScreen)];
    }

    private function chair(): array
    {
        $proposals = ReviewAssignment::where('status', AssignmentStatus::Proposed->value);
        $toConfirm = Decision::where('status', DecisionStatus::AwaitingConfirmation->value);

        $tiles = [
            $this->tile('Decisions to confirm', (clone $toConfirm)->count(),
                route('applications.index', ['status' => ApplicationStatus::AwaitingDecision->value])),
            $this->tile('Reviewer proposals to authorize', (clone $proposals)->count(),
                route('applications.index', ['status' => ApplicationStatus::ForReview->value])),
            $this->tile('For Review', Application::where('status', ApplicationStatus::ForReview->value)->count(),
                route('applications.index', ['status' => ApplicationStatus::ForReview->value])),
            $this->tile('Awaiting REC Decision', Application::where('status', ApplicationStatus::AwaitingDecision->value)->count(),
                route('applications.index', ['status' => ApplicationStatus::AwaitingDecision->value])),
        ];

        $items = (clone $proposals)->with('application:id,reference_no,title', 'reviewer:id,name')->oldest()->limit(10)->get()
            ->map(fn (ReviewAssignment $r) => [
                'url' => route('applications.show', $r->application).'#reviewers',
                'reference' => $r->application->reference_no,
                'title' => $r->application->title,
                'badge' => 'Authorize reviewer: '.$r->reviewer->name,
                'badgeClass' => 'text-bg-info',
            ]);

        $decisions = (clone $toConfirm)->with('application:id,reference_no,title')->oldest('sent_for_confirmation_at')->limit(10)->get()
            ->map(fn (Decision $d) => [
                'url' => route('decisions.show', $d),
                'reference' => $d->application->reference_no,
                'title' => $d->application->title,
                'badge' => 'Confirm decision: '.$d->categoryLabel(),
                'badgeClass' => 'text-bg-success',
            ]);

        return [$tiles, $decisions->concat($items)];
    }

    private function reviewer(User $user): array
    {
        $mine = ReviewAssignment::where('reviewer_id', $user->id);

        $tiles = [
            $this->tile('Invitations to answer', (clone $mine)->where('status', AssignmentStatus::Authorized->value)->count(), route('reviews.index')),
            $this->tile('Reviews in progress', (clone $mine)->where('status', AssignmentStatus::Accepted->value)->count(), route('reviews.index')),
            $this->tile('Overdue', (clone $mine)->overdue()->count(), route('reviews.index'), danger: true),
            $this->tile('Submitted', (clone $mine)->where('status', AssignmentStatus::Submitted->value)->count(), route('reviews.index')),
        ];

        $items = (clone $mine)->whereIn('status', [AssignmentStatus::Authorized->value, AssignmentStatus::Accepted->value])
            ->with('application:id,reference_no,title')->orderBy('due_at')->limit(10)->get()
            ->map(fn (ReviewAssignment $r) => [
                'url' => route('reviews.show', $r),
                'reference' => $r->application->reference_no,
                'title' => $r->application->title,
                'badge' => ($r->isOverdue() ? 'Overdue · ' : '').$r->status->label().' · due '.$r->due_at->format('M j'),
                'badgeClass' => $r->isOverdue() ? 'text-bg-danger' : $r->status->badgeClass(),
            ]);

        return [$tiles, $items];
    }

    private function applicationItem(Application $application, ?string $badge = null): array
    {
        return [
            'url' => route('applications.show', $application),
            'reference' => $application->displayReference(),
            'title' => $application->title,
            'badge' => $badge ?? $application->status->label(),
            'badgeClass' => $badge ? 'text-bg-primary' : $application->status->badgeClass(),
            'since' => $application->latestStatusChange?->created_at,
        ];
    }

    private function tile(string $label, int $value, string $url, bool $danger = false): array
    {
        return compact('label', 'value', 'url', 'danger');
    }
}
