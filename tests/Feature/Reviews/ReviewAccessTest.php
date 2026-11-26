<?php

namespace Tests\Feature\Reviews;

use App\Enums\Role;
use App\Models\Application;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * Reviewers open only authorized assignments and the assigned version;
 * review reports and reviewer identities stay internal.
 */
class ReviewAccessTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private User $reviewer;

    private Application $application;

    private ReviewAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->reviewer = User::factory()->reviewer()->create(['name' => 'Reviewer Rosa Reyes']);
        $this->application = $this->forReviewApplication();

        $service = app(ReviewService::class);
        $this->assignment = $service->propose($this->application, User::factory()->secretariat()->create(), $this->reviewer, today()->addWeek());
        $service->approve($this->assignment, User::factory()->chair()->create());
        $service->respond($this->assignment, $this->reviewer, true, false, null, null);
        $service->uploadReviewFile($this->assignment, $this->reviewer, 'form_4a', $this->pdf('form-4a.pdf'));
        $service->submitReview($this->assignment, $this->reviewer, 'approve', 'Well prepared.');
    }

    public function test_reviewer_cannot_open_the_application_page_or_other_applications_files(): void
    {
        $other = $this->submittedApplication();
        $otherDocument = $other->latestSubmittedSet->documents->first();

        $this->actingAs($this->reviewer)->get("/applications/{$this->application->id}")->assertForbidden();
        $this->actingAs($this->reviewer)->get("/documents/{$otherDocument->id}/download")->assertForbidden();
    }

    public function test_reviewer_downloads_only_the_assigned_version(): void
    {
        $assigned = $this->assignment->documentSet->documents->first();
        $this->actingAs($this->reviewer)->get("/documents/{$assigned->id}/download")->assertOk();

        // A later version of the same application is not part of this assignment.
        $this->application->forceFill(['status' => 'for_revision'])->save();
        $researcher = $this->application->researcher;
        $service = app(ApplicationService::class);
        $service->uploadDocument($this->application, $researcher, 'response_letter', $this->pdf('response.pdf'));
        $service->submit($this->application->fresh(), $researcher);
        $versionTwoDocument = $this->application->fresh()->latestSubmittedSet->documents->first();

        $this->assertNotSame($assigned->document_set_id, $versionTwoDocument->document_set_id);
        $this->actingAs($this->reviewer)->get("/documents/{$versionTwoDocument->id}/download")->assertForbidden();
    }

    public function test_other_reviewers_cannot_see_the_assignment_or_its_review_forms(): void
    {
        $otherReviewer = User::factory()->reviewer()->create();
        $file = $this->assignment->files()->first();

        $this->actingAs($otherReviewer)->get("/reviews/{$this->assignment->id}")->assertForbidden();
        $this->actingAs($otherReviewer)->get("/review-files/{$file->id}/download")->assertForbidden();
    }

    public function test_researcher_never_sees_reviewer_identity_or_review_forms(): void
    {
        $researcher = $this->application->researcher;
        $file = $this->assignment->files()->first();

        $this->actingAs($researcher)->get("/applications/{$this->application->id}")
            ->assertOk()
            ->assertDontSee('Reviewer Rosa Reyes')
            ->assertDontSee('Well prepared.')
            ->assertDontSee('form-4a.pdf');

        $this->actingAs($researcher)->get("/review-files/{$file->id}/download")->assertForbidden();
        $this->actingAs($researcher)->get("/reviews/{$this->assignment->id}")->assertForbidden();
    }

    public function test_it_administrator_cannot_open_review_forms(): void
    {
        $file = $this->assignment->files()->first();

        $this->actingAs(User::factory()->admin()->create(['is_main_admin' => true]))
            ->get("/review-files/{$file->id}/download")
            ->assertForbidden();
    }

    public function test_secretariat_and_chair_see_the_report(): void
    {
        $file = $this->assignment->files()->first();

        foreach ([Role::Secretariat, Role::Chair] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get("/applications/{$this->application->id}")
                ->assertOk()
                ->assertSee('Reviewer Rosa Reyes')
                ->assertSee('Approve')
                ->assertSee('Well prepared.');
            $this->actingAs($user)->get("/review-files/{$file->id}/download")->assertOk();
        }
    }

    public function test_reviews_page_is_for_reviewers_only(): void
    {
        $this->actingAs($this->application->researcher)->get('/reviews')->assertForbidden();
        $this->actingAs($this->reviewer)->get('/reviews')->assertOk()->assertSee($this->application->reference_no);
    }
}
