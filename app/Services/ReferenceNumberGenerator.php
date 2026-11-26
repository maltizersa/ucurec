<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Issues unique reference numbers such as UCUREC-2026-0001.
 * Numbering restarts each year. Call inside a database transaction.
 */
class ReferenceNumberGenerator
{
    public const PREFIX = 'UCUREC';

    public function next(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        DB::table('reference_counters')->insertOrIgnore(['year' => $year, 'last_number' => 0]);

        // Lock the row so two submissions at the same moment never share a number.
        $counter = DB::table('reference_counters')->where('year', $year)->lockForUpdate()->first();
        $number = $counter->last_number + 1;

        DB::table('reference_counters')->where('year', $year)->update(['last_number' => $number]);

        return sprintf('%s-%d-%04d', self::PREFIX, $year, $number);
    }
}
