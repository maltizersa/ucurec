<?php

namespace Tests\Feature\Records;

use App\Enums\Role;
use App\Models\User;
use App\Services\ReviewService;
use App\Support\CsvCell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

/**
 * Module 6: search and filters, overdue flag, and the CSV register.
 */
class TrackingAndRegisterTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private User $secretariat;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->secretariat = User::factory()->secretariat()->create();
    }

    private function csv(array $query = []): array
    {
        $response = $this->actingAs($this->secretariat)->get(route('applications.register', $query));
        $response->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = array_values(array_filter(explode("\n", substr($content, 3))));

        return array_map('str_getcsv', $lines);
    }

    public function test_register_lists_submitted_applications_but_not_drafts(): void
    {
        $submitted = $this->submittedApplication();
        $draft = $this->draftApplication();
        $draft->forceFill(['title' => 'Unsubmitted Private Draft'])->save();

        $rows = $this->csv();

        $this->assertSame('Reference No.', $rows[0][0]);
        $this->assertCount(2, $rows); // header + 1
        $this->assertSame($submitted->reference_no, $rows[1][0]);
        $this->assertSame('Submitted', $rows[1][7]);
        $this->assertSame('1', $rows[1][10]); // versions submitted
        $this->assertStringNotContainsString($draft->title, implode(',', array_merge(...$rows)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'register.exported', 'user_id' => $this->secretariat->id]);
    }

    public function test_register_includes_the_released_decision_but_no_internal_details(): void
    {
        $application = $this->awaitingDecisionApplication();
        $chair = User::factory()->chair()->create();
        $decisions = app(\App\Services\DecisionService::class);
        $decision = $decisions->create($application, $this->secretariat, [
            'category' => 'approved',
            'decision_date' => today()->toDateString(),
            'approval_date' => today()->toDateString(),
            'expiry_date' => today()->addYear()->toDateString(),
            'basis' => 'INTERNAL-BASIS-TEXT',
        ]);
        $decisions->uploadFile($decision, $this->secretariat, 'letter', $this->pdf('letter.pdf'));
        $decisions->sendForConfirmation($decision, $this->secretariat);
        $decisions->confirmAndRelease($decision, $chair);

        $rows = $this->csv();
        $flat = implode(',', array_merge(...$rows));

        $this->assertSame('Approved', $rows[1][11]);
        $this->assertSame(today()->addYear()->toDateString(), $rows[1][14]);
        $this->assertStringNotContainsString('INTERNAL-BASIS-TEXT', $flat);
        $this->assertStringNotContainsString('juan@example.com', $flat, 'PI contact details are not exported.');

        // Reviewer names are never in the register.
        $reviewerName = $application->reviewAssignments()->first()->reviewer->name;
        $this->assertStringNotContainsString($reviewerName, $flat);
    }

    public function test_register_follows_the_list_filters(): void
    {
        $nursing = $this->submittedApplication();
        $nursing->forceFill(['college_program' => 'College of Nursing'])->save();
        $this->submittedApplication();

        $rows = $this->csv(['college' => 'College of Nursing']);

        $this->assertCount(2, $rows);
        $this->assertSame($nursing->reference_no, $rows[1][0]);
    }

    public function test_spreadsheet_formulas_are_neutralized(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", CsvCell::safe('=HYPERLINK("x")'));
        $this->assertSame("'+1", CsvCell::safe('+1'));
        $this->assertSame("'@SUM(A1)", CsvCell::safe('@SUM(A1)'));
        $this->assertSame('Normal title', CsvCell::safe('Normal title'));

        $application = $this->submittedApplication();
        $application->forceFill(['title' => '=cmd|calc'])->save();

        $rows = $this->csv();
        $this->assertSame("'=cmd|calc", $rows[1][1]);
    }

    public function test_only_secretariat_and_chair_can_export(): void
    {
        $this->submittedApplication();

        foreach ([Role::Researcher, Role::Reviewer, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('applications.register'))
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->chair()->create())->get(route('applications.register'))->assertOk();
    }

    public function test_search_and_filters_on_the_list(): void
    {
        $first = $this->submittedApplication();
        $first->forceFill(['title' => 'Sleep Quality Study', 'research_type' => 'Faculty research'])->save();
        $second = $this->submittedApplication();

        $this->actingAs($this->secretariat)->get('/applications?q=Sleep')
            ->assertSee($first->reference_no)->assertDontSee($second->reference_no);

        $this->actingAs($this->secretariat)->get('/applications?type=Faculty+research')
            ->assertSee($first->reference_no)->assertDontSee($second->reference_no);

        $this->actingAs($this->secretariat)->get('/applications?from='.today()->addDay()->toDateString())
            ->assertDontSee($first->reference_no)->assertSee('No applications match');
    }

    public function test_overdue_filter_and_flag(): void
    {
        $application = $this->forReviewApplication();
        $other = $this->forReviewApplication();

        $reviews = app(ReviewService::class);
        $assignment = $reviews->propose($application, $this->secretariat, User::factory()->reviewer()->create(), today()->addWeek());
        $reviews->approve($assignment, User::factory()->chair()->create());
        $assignment->update(['due_at' => today()->subDays(3)]);

        $this->actingAs($this->secretariat)->get('/applications?overdue=1')
            ->assertOk()
            ->assertSee($application->reference_no)
            ->assertDontSee($other->reference_no)
            ->assertSee('Overdue review');
    }

    public function test_researcher_list_has_no_staff_filters_or_export(): void
    {
        $application = $this->submittedApplication();

        $this->actingAs($application->researcher)->get('/applications')
            ->assertOk()
            ->assertDontSee('Download CSV register')
            ->assertDontSee('Overdue review');
    }

    public function test_status_history_and_days_in_status_are_shown(): void
    {
        $application = $this->forReviewApplication();

        $this->actingAs($this->secretariat)->get('/applications')->assertSee('In status since');
        $this->actingAs($this->secretariat)->get("/applications/{$application->id}")
            ->assertSee('Status history')
            ->assertSee('Screening complete; ready for reviewer assignment.')
            ->assertSee('Print record');
    }
}
