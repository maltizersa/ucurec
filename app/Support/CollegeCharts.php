<?php

namespace App\Support;

use App\Enums\DecisionStatus;
use App\Enums\Role;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Counts per college / program for the dashboard pie charts.
 *
 * Each college keeps the same color in every chart (color follows the
 * college, not its rank). Colors: a categorical palette checked for
 * color-blind separation on a white card; the listed counts beside each
 * chart carry the values, so color is never the only cue.
 */
class CollegeCharts
{
    /** Fixed categorical order, one per configured college. */
    private const PALETTE = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];

    /** Colleges past the palette, unknown or blank values. */
    private const NEUTRAL = '#898781';

    /**
     * @return array{title: string, subtitle: string, total: int, slices: list<array{label: string, value: int, percent: float, color: string}>}
     */
    public function researcherAccounts(): array
    {
        $counts = User::role(Role::Researcher)
            ->selectRaw('college_program, COUNT(*) as total')
            ->groupBy('college_program')
            ->pluck('total', 'college_program');

        return $this->chart('Researcher accounts', 'Accounts created, by college / program', $counts->all());
    }

    public function submittedApplications(): array
    {
        return $this->chart(
            'Submitted applications',
            'Applications sent to UCUREC, by college / program',
            $this->countByCollege(Application::query()->whereNotNull('reference_no')),
        );
    }

    /**
     * Applications whose released REC decision counts as passed
     * (Approved or Exempted; see `passed` in config/ucurec.php).
     */
    public function passedApplications(): array
    {
        $passed = collect(config('ucurec.decision_categories'))->filter(fn ($c) => $c['passed'] ?? false)->keys()->all();

        $query = Application::query()->whereHas('decisions', fn (Builder $q) => $q
            ->where('status', DecisionStatus::Released->value)
            ->whereIn('category', $passed));

        return $this->chart('Passed (approved)', 'Released Approved or Exempted decisions, by college / program', $this->countByCollege($query));
    }

    /**
     * @return array<string, int>
     */
    private function countByCollege(Builder $query): array
    {
        return $query->selectRaw('college_program, COUNT(*) as total')
            ->groupBy('college_program')
            ->pluck('total', 'college_program')
            ->all();
    }

    /**
     * @param  array<string|int, int|string>  $counts  college => count
     */
    private function chart(string $title, string $subtitle, array $counts): array
    {
        $colleges = config('ucurec.colleges');
        $slices = [];
        $other = 0;

        // Configured colleges first, in their fixed order and color.
        foreach ($colleges as $index => $college) {
            $value = (int) ($counts[$college] ?? 0);
            if ($value > 0 && isset(self::PALETTE[$index])) {
                $slices[] = ['label' => $college, 'value' => $value, 'color' => self::PALETTE[$index]];
            } elseif ($value > 0) {
                $other += $value;
            }
        }

        // Anything else (blank, renamed or extra colleges) shares one neutral slice.
        foreach ($counts as $college => $value) {
            if (! in_array($college, $colleges, true)) {
                $other += (int) $value;
            }
        }
        if ($other > 0) {
            $slices[] = ['label' => 'Other / not specified', 'value' => $other, 'color' => self::NEUTRAL];
        }

        $total = array_sum(array_column($slices, 'value'));
        foreach ($slices as &$slice) {
            $slice['percent'] = $total > 0 ? round($slice['value'] * 100 / $total, 1) : 0.0;
        }

        return compact('title', 'subtitle', 'total', 'slices');
    }
}
