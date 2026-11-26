<?php

namespace App\Support;

/**
 * Validation for every uploaded file: REC-approved types only (checked by
 * extension and by content) and a size limit. Settings: config/ucurec.php.
 */
class UploadRules
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        $uploads = config('ucurec.uploads');
        $types = implode(',', $uploads['extensions']);

        return ['required', 'file', "extensions:{$types}", "mimes:{$types}", 'max:'.$uploads['max_kb']];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'file'): array
    {
        $uploads = config('ucurec.uploads');
        $types = strtoupper(implode(', ', $uploads['extensions']));

        return [
            "{$field}.extensions" => "Only {$types} files are accepted.",
            "{$field}.mimes" => "The file content does not match an accepted type ({$types}).",
            "{$field}.max" => 'Each file must be '.($uploads['max_kb'] / 1024).' MB or smaller.',
        ];
    }
}
