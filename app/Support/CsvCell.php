<?php

namespace App\Support;

/**
 * Protects spreadsheet users from CSV (formula) injection: a cell that a
 * spreadsheet would treat as a formula is prefixed with an apostrophe.
 */
class CsvCell
{
    public static function safe(?string $value): string
    {
        $value ??= '';

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
