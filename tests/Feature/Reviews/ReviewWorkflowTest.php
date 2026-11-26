<?php

namespace Tests\Feature\Reviews;

use App\Enums\ApplicationStatus;
use App\Enums\AssignmentStatus;
use App\Models\Application;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

class ReviewWorkflowTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private User $secretariat;

    private User $chair;

    private User $reviewer;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->secretariat = User::factory()->secretariat()->create();
        $this->chair = User::factory()->chair()->create();
        $this->reviewer = User::factory()->reviewer()->create();
        $this->application = $this->forReviewApplication();
    }

    private function propose(?User $reviewer = null): ReviewAssignment
    {
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/assignments", [
            'reviewer_id' => ($reviewer ?? $this->reviewer)->id,
            'due_at' => today()->addDays(14)->toDateString(),
        ])->assertSessionHasNoErrors();

        return ReviewAssignment::latest('id')->first();
    }

    private function authorizedAssignment(?User $reviewer = null): ReviewAssignment
    {
        $assignment = $this->propose($reviewer);
        $this->actingAs($this->chair)->post("/assignments/{$assignment->id}/approve")->assertRedirect();

        return $assignment->fresh();
    }

    private function acceptedAssignment(): ReviewAssignment
    {
        $assignment = $this->authorizedAssignment();
        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/respond", [
            'decision' => 'accept', 'has_conflict' => 'no',
        ])->assertRedirect();

        return $assignment->fresh();
    }

    public function test_secretariat_proposes_the_current_version_and_the_chair_authorizes(): void
    {
        $assignment = $this->propose();

        $this->assertSame(AssignmentStatus::Proposed, $assignment->status);
        $this->assertSame($this->application->latestSubmittedSet->id, $assignment->document_set_id);

        // The reviewer does not see a proposal before the Chair authorizes it.
        $this->actingAs($this->reviewer)->get("/reviews/{$assignment->id}")->assertForbidden();
        $this->actingAs($this->reviewer)->get('/reviews')->assertDontSee($this->application->reference_no);

        // Only the Chair authorizes.
        $this->actingAs($this->secretariat)->post("/assignments/{$assignment->id}/approve")->assertForbidden();
        $this->actingAs($this->chair)->post("/assignments/{$assignment->id}/approve")->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Authorized, $assignment->status);
        $this->assertSame($this->chair->id, $assignment->authorized_by);
        $this->actingAs($this->reviewer)->get("/reviews/{$assignment->id}")->assertOk()->assertSee('Conflict of interest declaration');
    }

    public function test_only_the_secretariat_proposes_and_only_while_for_review(): void
    {
        $this->actingAs($this->chair)->post("/applications/{$this->application->id}/assignments", [
            'reviewer_id' => $this->reviewer->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertForbidden();

        $submitted = $this->submittedApplication();
        $this->actingAs($this->secretariat)->post("/applications/{$submitted->id}/assignments", [
            'reviewer_id' => $this->reviewer->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertForbidden();

        $this->assertDatabaseCount('review_assignments', 0);
    }

    public function test_only_reviewer_accounts_can_be_proposed_and_not_twice(): void
    {
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/assignments", [
            'reviewer_id' => $this->chair->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('reviewer_id');

        $this->propose();
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/assignments", [
            'reviewer_id' => $this->reviewer->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('reviewer_id');

        $this->assertDatabaseCount('review_assignments', 1);
    }

    public function test_chair_can_decline_to_authorize_with_a_reason(): void
    {
        $assignment = $this->propose();

        $this->actingAs($this->chair)->post("/assignments/{$assignment->id}/reject")->assertSessionHasErrors('note');
        $this->actingAs($this->chair)->post("/assignments/{$assignment->id}/reject", ['note' => 'Same department as the PI.'])->assertRedirect();

        $this->assertSame(AssignmentStatus::NotAuthorized, $assignment->fresh()->status);
        $this->actingAs($this->reviewer)->get("/reviews/{$assignment->id}")->assertForbidden();
    }

    public function test_reviewer_must_declare_conflict_of_interest_before_accepting(): void
    {
        $assignment = $this->authorizedAssignment();

        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/respond", ['decision' => 'accept'])
            ->assertSessionHasErrors('has_conflict');

        $this->assertSame(AssignmentStatus::Authorized, $assignment->fresh()->status);
    }

    public function test_declared_conflict_blocks_the_reviewer_and_allows_reassignment(): void
    {
        $assignment = $this->authorizedAssignment();
        $document = $this->application->latestSubmittedSet->documents->first();

        // Even when clicking "Accept", a declared conflict closes the assignment.
        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/respond", [
            'decision' => 'accept', 'has_conflict' => 'yes', 'conflict_details' => 'I am the adviser of the PI.',
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Conflicted, $assignment->status);
        $this->actingAs($this->reviewer)->get("/documents/{$document->id}/download")->assertForbidden();
        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/submit", ['recommendation' => 'approve', 'confirm' => 1])->assertForbidden();

        // The conflict details are not written to the audit log.
        $this->assertDatabaseMissing('audit_logs', ['properties' => json_encode(['conflict_details' => 'I am the adviser of the PI.'])]);
        $this->assertStringNotContainsString('adviser of the PI', \App\Models\AuditLog::pluck('properties')->toJson());

        // The conflicted reviewer cannot be proposed again; someone else can.
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/assignments", [
            'reviewer_id' => $this->reviewer->id, 'due_at' => today()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('reviewer_id');

        $this->propose(User::factory()->reviewer()->create());
        $this->assertSame(2, ReviewAssignment::count());
    }

    public function test_full_review_and_forward_for_decision(): void
    {
        $assignment = $this->acceptedAssignment();
        $this->assertSame(AssignmentStatus::Accepted, $assignment->status);

        // Cannot submit without an uploaded form.
        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/submit", [
            'recommendation' => 'minor_modifications', 'confirm' => 1,
        ])->assertSessionHasErrors('submit');

        // Cannot forward while the review is open.
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/forward")
            ->assertSessionHasErrors('forward');

        $this->actingAs($this->reviewer)->post("/reviews/{$assignment->id}/files", [
            'form_type' => 'form_4a', 'file' => $this->pdf('Form 4A.pdf'),
        ])->assertSessionHasNoErrors();

        $this->post("/reviews/{$assignment->id}/submit", [
            'recommendation' => 'minor_modifications',
            'review_comments' => 'Clarify the sampling method.',
            'confirm' => 1,
        ])->assertRedirect();

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Submitted, $assignment->status);
        $this->assertSame('minor_modifications', $assignment->recommendation);

        // Locked after submitting.
        $this->post("/reviews/{$assignment->id}/files", ['form_type' => 'form_4b', 'file' => $this->pdf()])->assertForbidden();
        $file = $assignment->files()->first();
        $this->delete("/reviews/{$assignment->id}/files/{$file->id}")->assertForbidden();

        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/forward")->assertRedirect();

        $application = $this->application->fresh();
        $this->assertSame(ApplicationStatus::AwaitingDecision, $application->status);
    }

    public function test_secretariat_cancels_an_open_assignment_and_the_reviewer_loses_access(): void
    {
        $assignment = $this->acceptedAssignment();
        $document = $this->application->latestSubmittedSet->documents->first();
        $this->actingAs($this->reviewer)->get("/documents/{$document->id}/download")->assertOk();

        $this->actingAs($this->secretariat)->post("/assignments/{$assignment->id}/cancel", ['reason' => 'Overdue; reassigned.'])->assertRedirect();

        $this->assertSame(AssignmentStatus::Cancelled, $assignment->fresh()->status);
        $this->actingAs($this->reviewer)->get("/documents/{$document->id}/download")->assertForbidden();
    }

    public function test_overdue_assignments_are_flagged(): void
    {
        $assignment = $this->authorizedAssignment();
        $assignment->update(['due_at' => today()->subDay()]);

        $this->assertTrue($assignment->fresh()->isOverdue());
        $this->assertSame(1, ReviewAssignment::overdue()->count());

        $this->actingAs($this->secretariat)->get("/applications/{$this->application->id}")->assertSee('Overdue');
        $this->actingAs($this->secretariat)->get('/dashboard')->assertSee('Review overdue since');
        $this->actingAs($this->reviewer)->get('/reviews')->assertSee('Overdue');
    }

    public function test_exempt_route_can_be_forwarded_without_reviews(): void
    {
        $this->actingAs($this->secretariat)->post("/applications/{$this->application->id}/review-route", ['review_route' => 'exempt']);
        $this->post("/applications/{$this->application->id}/forward")->assertRedirect();

        $this->assertSame(ApplicationStatus::AwaitingDecision, $this->application->fresh()->status);
    }
}
