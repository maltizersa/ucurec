<?php

namespace App\Support;

/**
 * Human-readable file sizes without the intl extension (Laravel's
 * Number::fileSize needs intl, which XAMPP does not enable by default).
 */
class FileSize
{
    public static function format(int $bytes): string
    {
        $size = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) (int) $size : number_format($size, 1)).' '.$units[$unit];
    }
}
