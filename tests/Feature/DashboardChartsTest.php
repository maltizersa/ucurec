<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\DecisionService;
use App\Support\CollegeCharts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesApplications;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use CreatesApplications, RefreshDatabase;

    private function slices(array $chart): array
    {
        return collect($chart['slices'])->pluck('value', 'label')->all();
    }

    public function test_researcher_accounts_are_counted_per_college(): void
    {
        [$nursing, $education] = [config('ucurec.colleges')[6], config('ucurec.colleges')[4]];
        User::factory()->count(3)->create(['college_program' => $nursing]);
        User::factory()->pending()->create(['college_program' => $education]);
        User::factory()->create(['college_program' => null]);
        User::factory()->reviewer()->create(['college_program' => $nursing]); // staff are not counted

        $chart = app(CollegeCharts::class)->researcherAccounts();

        $this->assertSame(5, $chart['total']);
        $this->assertSame([$education => 1, $nursing => 3, 'Other / not specified' => 1], $this->slices($chart));
        $this->assertSame(60.0, collect($chart['slices'])->firstWhere('label', $nursing)['percent']);
    }

    public function test_each_college_keeps_its_color_in_every_chart(): void
    {
        Storage::fake('documents');
        $nursing = config('ucurec.colleges')[6];
        $application = $this->submittedApplication(User::factory()->create(['college_program' => $nursing]));
        $application->forceFill(['college_program' => $nursing])->save();

        $charts = app(CollegeCharts::class);
        $accountColor = collect($charts->researcherAccounts()['slices'])->firstWhere('label', $nursing)['color'];
        $submittedColor = collect($charts->submittedApplications()['slices'])->firstWhere('label', $nursing)['color'];

        $this->assertSame($accountColor, $submittedColor);
    }

    public function test_submitted_and_passed_applications_per_college(): void
    {
        Storage::fake('documents');

        $draft = $this->draftApplication(); // not submitted: not counted
        $approved = $this->awaitingDecisionApplication();
        $revision = $this->awaitingDecisionApplication();
        $revision->forceFill(['college_program' => 'College of Nursing'])->save();

        $secretariat = User::factory()->secretariat()->create();
        $chair = User::factory()->chair()->create();
        $decisions = app(DecisionService::class);
        foreach ([[$approved, 'approved'], [$revision, 'minor_modifications']] as [$application, $category]) {
            $decision = $decisions->create($application, $secretariat, [
                'category' => $category,
                'decision_date' => today()->toDateString(),
                'approval_date' => today()->toDateString(),
                'consolidated_comments' => 'Comments.',
            ]);
            $decisions->uploadFile($decision, $secretariat, 'letter', $this->pdf('letter.pdf'));
            $decisions->sendForConfirmation($decision, $secretariat);
            $decisions->confirmAndRelease($decision, $chair);
        }

        $charts = app(CollegeCharts::class);

        $submitted = $this->slices($charts->submittedApplications());
        $this->assertSame([$approved->college_program => 1, 'College of Nursing' => 1], $submitted);

        // Only the released Approved decision counts as passed.
        $passed = $this->slices($charts->passedApplications());
        $this->assertSame([$approved->college_program => 1], $passed);
    }

    public function test_who_sees_which_charts(): void
    {
        foreach ([Role::Secretariat, Role::Chair] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/dashboard')
                ->assertSee('Researcher accounts')
                ->assertSee('Submitted applications')
                ->assertSee('Passed (approved)');
        }

        // The IT administrator sees account counts only.
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')
            ->assertSee('Researcher accounts')
            ->assertDontSee('Submitted applications')
            ->assertDontSee('Passed (approved)');

        foreach ([Role::Researcher, Role::Reviewer] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/dashboard')
                ->assertDontSee('By college / program');
        }
    }

    public function test_empty_charts_say_so(): void
    {
        $this->actingAs(User::factory()->secretariat()->create())->get('/dashboard')
            ->assertSee('No data yet');
    }
}
