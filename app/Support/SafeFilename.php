<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Cleans an uploaded file's name for display and download only.
 * Files are always stored under a random name.
 */
class SafeFilename
{
    /**
     * No path, no control or reserved characters, and the extension that
     * was actually validated.
     */
    public static function make(string $name, string $extension): string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
        $base = trim(preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]+/u', ' ', $base) ?? '');
        $base = Str::limit($base !== '' ? $base : 'document', 180, '');

        return $base.'.'.$extension;
    }
}
