<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Support\ApplicationFilters;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Simple CSV application register (Module 6), using the same filters as
 * the applications list. It contains the record fields only: no reviewer
 * identities, no contact details, no internal decision basis.
 */
class ApplicationRegisterController extends Controller
{
    private const HEADERS = [
        'Reference No.',
        'Title',
        'Principal Investigator',
        'Adviser',
        'College / Program',
        'Research Type',
        'Date Submitted',
        'Current Status',
        'In Status Since',
        'Review Route',
        'Versions Submitted',
        'Latest REC Decision',
        'Decision Date',
        'Approval Date',
        'Approval Expiry',
        'Decision Released',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorize('exportRegister', Application::class);

        $filters = new ApplicationFilters($request);
        $query = $filters->apply(Application::query()->visibleTo($request->user()))
            ->with(['latestStatusChange', 'latestReleasedDecision'])
            ->withCount(['documentSets as versions_submitted' => fn ($q) => $q->whereNotNull('submitted_at')]);

        AuditLog::record('register.exported', null, 'Downloaded the CSV application register.', [
            'rows' => (clone $query)->count(),
            'filters' => $filters->query(),
        ]);

        $filename = 'ucurec-application-register-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows accents and ñ correctly
            fputcsv($out, self::HEADERS, ',', '"', '');

            foreach ($query->lazy(200) as $application) {
                fputcsv($out, array_map([CsvCell::class, 'safe'], $this->row($application)), ',', '"', '');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return list<string>
     */
    private function row(Application $application): array
    {
        $decision = $application->latestReleasedDecision;
        $date = fn ($value) => $value?->format('Y-m-d') ?? '';

        return [
            $application->reference_no ?? '',
            $application->title,
            $application->principal_investigator,
            $application->adviser ?? '',
            $application->college_program,
            $application->research_type,
            $date($application->submitted_at),
            $application->status->label(),
            $date($application->latestStatusChange?->created_at),
            $application->review_route ? config('ucurec.review_routes.'.$application->review_route, $application->review_route) : '',
            (string) $application->versions_submitted,
            $decision?->categoryLabel() ?? '',
            $date($decision?->decision_date),
            $date($decision?->approval_date),
            $date($decision?->expiry_date),
            $date($decision?->released_at),
        ];
    }
}
