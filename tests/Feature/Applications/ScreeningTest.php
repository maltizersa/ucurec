<?php

namespace Tests\Feature\Applications;

use App\Enums\ApplicationStatus;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

class ScreeningTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private User $secretariat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->secretariat = User::factory()->secretariat()->create();
    }

    public function test_secretariat_starts_screening(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs($this->secretariat)
            ->post("/applications/{$application->id}/screening/start")
            ->assertRedirect("/applications/{$application->id}/screening");

        $this->assertSame(ApplicationStatus::ForScreening, $application->fresh()->status);
        $this->actingAs($this->secretariat)->get("/applications/{$application->id}/screening")->assertOk()->assertSee('completeness checklist');
    }

    public function test_chair_can_view_but_not_screen(): void
    {
        $application = $this->submittedApplication();
        $chair = User::factory()->chair()->create();

        $this->actingAs($chair)->get("/applications/{$application->id}")->assertOk();
        $this->actingAs($chair)->post("/applications/{$application->id}/screening/start")->assertForbidden();
    }

    public function test_every_checklist_item_must_be_marked(): void
    {
        $application = $this->submittedApplication();
        $this->actingAs($this->secretariat)->post("/applications/{$application->id}/screening/start");

        $items = $this->checklistAnswers();
        unset($items['cv']);

        $this->post("/applications/{$application->id}/screening", ['items' => $items])
            ->assertSessionHasErrors('items.cv.state');

        $this->assertSame(ApplicationStatus::ForScreening, $application->fresh()->status);
    }

    public function test_missing_items_return_the_application_as_incomplete_with_a_clear_list(): void
    {
        $application = $this->submittedApplication();
        $this->actingAs($this->secretariat)->post("/applications/{$application->id}/screening/start");

        $items = $this->checklistAnswers(overrides: ['cv' => Screening::STATE_MISSING, 'endorsement' => Screening::STATE_NOT_APPLICABLE]);
        $items['cv']['note'] = 'Please attach a signed CV.';

        $this->post("/applications/{$application->id}/screening", ['items' => $items, 'remarks' => 'Almost there.'])
            ->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Incomplete, $application->status);

        $screening = Screening::sole();
        $this->assertSame(Screening::RESULT_INCOMPLETE, $screening->result);
        $this->assertSame(['cv'], array_column($screening->missingItems(), 'key'));

        // The researcher sees the list, the note and the remarks, but not the screener's name.
        $this->actingAs($application->researcher)->get("/applications/{$application->id}")
            ->assertOk()
            ->assertSee('returned your application as incomplete')
            ->assertSee('Curriculum vitae of the principal investigator')
            ->assertSee('Please attach a signed CV.')
            ->assertSee('Almost there.')
            ->assertDontSee($this->secretariat->name);
    }

    public function test_resubmission_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $application = $this->submittedApplication();
        $reference = $application->reference_no;
        $this->actingAs($this->secretariat)->post("/applications/{$application->id}/screening/start");
        $this->post("/applications/{$application->id}/screening", [
            'items' => $this->checklistAnswers(overrides: ['cv' => Screening::STATE_MISSING]),
        ]);

        $researcher = $application->researcher;
        $versionOne = $application->latestSubmittedSet()->first();
        $versionOneFiles = $versionOne->documents()->pluck('stored_path')->sort()->values()->all();

        // The researcher can edit again and uploads the missing CV.
        $this->actingAs($researcher)->post("/applications/{$application->id}/documents", ['category' => 'cv', 'file' => $this->pdf('cv.pdf')])
            ->assertRedirect();
        $this->post("/applications/{$application->id}/submit", ['confirm' => '1', 'note' => 'CV attached.'])
            ->assertRedirect();

        $application->refresh();
        $versionTwo = $application->latestSubmittedSet()->first();

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame($reference, $application->reference_no, 'A resubmission is not a new application.');
        $this->assertSame(2, $versionTwo->version);
        $this->assertSame('resubmission', $versionTwo->kind);
        $this->assertCount(4, $versionTwo->documents);
        $this->assertSame(3, $versionTwo->documents()->whereNotNull('carried_from_id')->count());

        // Version 1 is untouched.
        $this->assertSame($versionOneFiles, $versionOne->documents()->pluck('stored_path')->sort()->values()->all());
        foreach ($versionOneFiles as $path) {
            Storage::disk('documents')->assertExists($path);
        }
    }

    public function test_complete_screening_moves_to_for_review_with_route(): void
    {
        $application = $this->submittedApplication();
        $this->actingAs($this->secretariat)->post("/applications/{$application->id}/screening/start");

        $this->post("/applications/{$application->id}/screening", [
            'items' => $this->checklistAnswers(),
            'review_route' => 'expedited',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(ApplicationStatus::ForReview, $application->status);
        $this->assertSame('expedited', $application->review_route);
        $this->assertSame(
            ['draft', 'submitted', 'for_screening', 'for_review'],
            $application->statusHistories()->pluck('to_status')->map->value->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'screening.recorded', 'user_id' => $this->secretariat->id]);
    }

    public function test_screening_is_only_possible_while_for_screening(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs($this->secretariat)
            ->post("/applications/{$application->id}/screening", ['items' => $this->checklistAnswers()])
            ->assertForbidden();
    }
}
