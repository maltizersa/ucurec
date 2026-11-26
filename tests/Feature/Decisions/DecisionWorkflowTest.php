<?php

namespace Tests\Feature\Decisions;

use App\Enums\ApplicationStatus;
use App\Enums\DecisionStatus;
use App\Models\Application;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

class DecisionWorkflowTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private User $secretariat;

    private User $chair;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->secretariat = User::factory()->secretariat()->create();
        $this->chair = User::factory()->chair()->create();
        $this->application = $this->awaitingDecisionApplication();
    }

    private function recordDecision(array $overrides = []): Decision
    {
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/decisions", [
            'category' => 'approved',
            'decision_date' => today()->toDateString(),
            'approval_date' => today()->toDateString(),
            'expiry_date' => today()->addYear()->toDateString(),
            'basis' => 'Approved by expedited review.',
            ...$overrides,
        ])->assertSessionHasNoErrors();

        return Decision::latest('id')->first();
    }

    private function uploadLetter(Decision $decision, string $kind = Decision::FILE_LETTER, string $name = 'letter.pdf'): void
    {
        $this->actingAs($this->secretariat)->post("/decisions/{$decision->id}/files", [
            'kind' => $kind, 'file' => $this->pdf($name),
        ])->assertSessionHasNoErrors();
    }

    public function test_recommendations_never_create_a_decision_by_themselves(): void
    {
        $this->assertSame(ApplicationStatus::AwaitingDecision, $this->application->status);
        $this->assertDatabaseCount('decisions', 0);
    }

    public function test_approval_is_recorded_confirmed_and_released(): void
    {
        $decision = $this->recordDecision();
        $this->assertSame(DecisionStatus::Draft, $decision->status);
        $this->assertSame($this->application->latestSubmittedSet->id, $decision->document_set_id);

        // Not ready without the signed letter.
        $this->post("/decisions/{$decision->id}/send")->assertSessionHasErrors('decision');
        $this->assertSame(DecisionStatus::Draft, $decision->fresh()->status);

        $this->uploadLetter($decision);
        $this->post("/decisions/{$decision->id}/send")->assertRedirect();
        $this->assertSame(DecisionStatus::AwaitingConfirmation, $decision->fresh()->status);

        // The Secretariat cannot release; the Chair confirms.
        $this->post("/decisions/{$decision->id}/confirm")->assertForbidden();
        $this->actingAs($this->chair)->post("/decisions/{$decision->id}/confirm")->assertRedirect();

        $decision->refresh();
        $this->assertSame(DecisionStatus::Released, $decision->status);
        $this->assertSame($this->chair->id, $decision->confirmed_by);
        $this->assertNotNull($decision->released_at);
        $this->assertSame(ApplicationStatus::DecisionReleased, $this->application->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'decision.released', 'user_id' => $this->chair->id]);
    }

    public function test_approval_needs_an_approval_date(): void
    {
        $decision = $this->recordDecision(['approval_date' => null, 'expiry_date' => null]);
        $this->uploadLetter($decision);

        $this->post("/decisions/{$decision->id}/send")->assertSessionHasErrors('decision');
    }

    public function test_full_committee_route_needs_the_minutes_extract(): void
    {
        $this->application = $this->awaitingDecisionApplication(route: 'full');
        $decision = $this->recordDecision();
        $this->uploadLetter($decision);

        $this->post("/decisions/{$decision->id}/send")->assertSessionHasErrors('decision');

        $this->uploadLetter($decision, Decision::FILE_MINUTES, 'minutes-extract.pdf');
        $this->post("/decisions/{$decision->id}/send")->assertSessionHasNoErrors();
        $this->assertSame(DecisionStatus::AwaitingConfirmation, $decision->fresh()->status);
    }

    public function test_chair_returns_a_decision_with_a_note(): void
    {
        $decision = $this->recordDecision();
        $this->uploadLetter($decision);
        $this->post("/decisions/{$decision->id}/send");

        $this->actingAs($this->chair)->post("/decisions/{$decision->id}/return")->assertSessionHasErrors('chair_note');
        $this->actingAs($this->chair)->post("/decisions/{$decision->id}/return", ['chair_note' => 'Fix the expiry date.'])->assertRedirect();

        $decision->refresh();
        $this->assertSame(DecisionStatus::Draft, $decision->status);
        $this->assertSame('Fix the expiry date.', $decision->chair_note);
        $this->assertSame(ApplicationStatus::AwaitingDecision, $this->application->fresh()->status);

        $this->actingAs($this->secretariat)->get("/decisions/{$decision->id}")->assertOk()->assertSee('Fix the expiry date.');
        $this->actingAs($this->secretariat)->get('/dashboard')->assertSee('Decision returned by the Chair');
    }

    public function test_only_one_open_decision_and_only_by_the_secretariat(): void
    {
        $this->actingAs($this->chair)->get("/applications/{$this->application->id}/decisions/create")->assertForbidden();

        $this->recordDecision();
        $this->actingAs($this->secretariat)->get("/applications/{$this->application->id}/decisions/create")->assertForbidden();
    }

    public function test_revision_cycle_keeps_the_same_application(): void
    {
        $reference = $this->application->reference_no;

        // Decision: minor modifications, with consolidated comments.
        $decision = $this->recordDecision([
            'category' => 'minor_modifications',
            'approval_date' => null,
            'expiry_date' => null,
            'consolidated_comments' => 'Revise the consent form to explain withdrawal.',
        ]);
        $this->uploadLetter($decision);
        $this->post("/decisions/{$decision->id}/send");
        $this->actingAs($this->chair)->post("/decisions/{$decision->id}/confirm");

        $application = $this->application->fresh();
        $this->assertSame(ApplicationStatus::ForRevision, $application->status);

        // The researcher sees the request and the comments.
        $researcher = $application->researcher;
        $this->actingAs($researcher)->get("/applications/{$application->id}")
            ->assertOk()
            ->assertSee('UCUREC requests revisions')
            ->assertSee('Revise the consent form to explain withdrawal.');

        // A revision needs the response document.
        $this->actingAs($researcher)->post("/applications/{$application->id}/documents", [
            'category' => 'informed_consent', 'file' => $this->pdf('consent-v2.pdf'),
        ]);
        $this->post("/applications/{$application->id}/submit", ['confirm' => 1])->assertSessionHasErrors('submit');

        $this->post("/applications/{$application->id}/documents", [
            'category' => 'response_letter', 'file' => $this->pdf('response.pdf'),
        ]);
        $this->post("/applications/{$application->id}/submit", ['confirm' => 1])->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame($reference, $application->reference_no);
        $this->assertSame('revision', $application->latestSubmittedSet->kind);
        $this->assertSame(2, $application->latestSubmittedSet->version);

        // The Secretariat routes the revised version back to review.
        $this->actingAs($this->secretariat)->get('/dashboard')->assertSee('Route revised version');
        $this->post("/applications/{$application->id}/route-revision", ['target' => 'review'])->assertRedirect();
        $this->assertSame(ApplicationStatus::ForReview, $application->fresh()->status);

        // New reviewers review version 2.
        $reviewer = User::factory()->reviewer()->create();
        $this->post("/applications/{$application->id}/assignments", [
            'reviewer_id' => $reviewer->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();
        $newest = $application->reviewAssignments()->reorder('id', 'desc')->first();
        $this->assertSame($reviewer->id, $newest->reviewer_id);
        $this->assertSame($application->latestSubmittedSet->id, $newest->document_set_id);
    }

    public function test_revised_version_can_go_straight_to_the_decision(): void
    {
        $decision = $this->recordDecision(['category' => 'minor_modifications', 'approval_date' => null, 'expiry_date' => null, 'consolidated_comments' => 'Minor edits.']);
        $this->uploadLetter($decision);
        $this->post("/decisions/{$decision->id}/send");
        $this->actingAs($this->chair)->post("/decisions/{$decision->id}/confirm");

        $application = $this->application->fresh();
        $researcher = $application->researcher;
        $this->actingAs($researcher)->post("/applications/{$application->id}/documents", ['category' => 'response_letter', 'file' => $this->pdf('response.pdf')]);
        $this->post("/applications/{$application->id}/submit", ['confirm' => 1]);

        // Only for revisions, only by the Secretariat.
        $this->actingAs($this->chair)->post("/applications/{$application->id}/route-revision", ['target' => 'decision'])->assertForbidden();
        $this->actingAs($this->secretariat)->post("/applications/{$application->id}/route-revision", ['target' => 'decision'])->assertRedirect();

        $this->assertSame(ApplicationStatus::AwaitingDecision, $application->fresh()->status);
        $this->actingAs($this->secretariat)->get("/applications/{$application->id}/decisions/create")->assertOk();
    }

    public function test_initial_submissions_cannot_skip_screening(): void
    {
        $submitted = $this->submittedApplication();

        $this->actingAs($this->secretariat)
            ->post("/applications/{$submitted->id}/route-revision", ['target' => 'review'])
            ->assertForbidden();
    }
}
