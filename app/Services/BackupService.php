<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Backup and restore of the whole record: every database table (as JSON
 * lines) and every uploaded file, in one zip with a manifest of SHA-256
 * checksums. Works the same on MySQL/MariaDB and SQLite, so the restore
 * can be tested automatically.
 */
class BackupService
{
    /** Short-lived or rebuildable tables that are not backed up. */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    private const PREFIX = 'ucurec-backup-';

    /**
     * @return array{name: string, path: string, size: int, tables: int, rows: int, documents: int}
     */
    public function create(string $prefix = self::PREFIX): array
    {
        $dir = $this->directory();
        File::ensureDirectoryExists($dir);

        $name = $prefix.now()->format('Ymd-His').'.zip';
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        $partial = $path.'.partial';
        $tempFiles = [];

        $zip = new ZipArchive;
        if ($zip->open($partial, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create the backup file in {$dir}.");
        }

        $manifest = [
            'app' => 'UCUREC Ethics Review',
            'created_at' => now()->toIso8601String(),
            'driver' => DB::connection()->getDriverName(),
            'migrations' => $this->migrations(),
            'encrypted' => $this->password() !== null,
            'tables' => [],
            'documents' => [],
        ];

        // 1. Database tables, one JSON object per line.
        $totalRows = 0;
        foreach ($this->tables() as $table) {
            $temp = tempnam(sys_get_temp_dir(), 'ucurec-bk');
            $tempFiles[] = $temp;
            $handle = fopen($temp, 'w');
            $rows = 0;

            foreach (DB::table($table)->cursor() as $row) {
                fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                $rows++;
            }
            fclose($handle);

            $entry = "database/{$table}.jsonl";
            $this->add($zip, $temp, $entry);
            $manifest['tables'][$table] = ['rows' => $rows, 'sha256' => hash_file('sha256', $temp)];
            $totalRows += $rows;
        }

        // 2. Uploaded documents, review forms, letters.
        $disk = Storage::disk(ApplicationService::DISK);
        foreach ($disk->allFiles() as $file) {
            $this->add($zip, $disk->path($file), 'documents/'.$file);
            $manifest['documents'][$file] = hash_file('sha256', $disk->path($file));
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->encrypt($zip, 'manifest.json');

        if (! $zip->close()) {
            throw new RuntimeException('Writing the backup archive failed.');
        }

        foreach ($tempFiles as $temp) {
            @unlink($temp);
        }
        rename($partial, $path);

        $this->prune();

        $result = [
            'name' => $name,
            'path' => $path,
            'size' => filesize($path),
            'tables' => count($manifest['tables']),
            'rows' => $totalRows,
            'documents' => count($manifest['documents']),
        ];

        AuditLog::record('backup.created', null, 'Created a backup of the database and documents.', [
            'name' => $name,
            'size' => $result['size'],
            'rows' => $totalRows,
            'documents' => $result['documents'],
        ]);

        return $result;
    }

    /**
     * Checks every entry against the manifest. Nothing is changed.
     *
     * @return array<string, mixed> the manifest
     */
    public function verify(string $path): array
    {
        $zip = $this->open($path);

        try {
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || ! isset($manifest['tables'], $manifest['documents'])) {
                throw new RuntimeException('The backup has no valid manifest (wrong file or wrong BACKUP_PASSWORD).');
            }

            foreach ($manifest['tables'] as $table => $info) {
                $this->assertEntry($zip, "database/{$table}.jsonl", $info['sha256']);
            }
            foreach ($manifest['documents'] as $file => $sha256) {
                $this->assertSafePath($file);
                $this->assertEntry($zip, 'documents/'.$file, $sha256);
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * Replaces all records and documents with the backup's contents.
     * A safety backup of the current state is made first.
     *
     * @return array{safety_backup: string, rows: int, documents: int}
     */
    public function restore(string $path): array
    {
        $manifest = $this->verify($path);

        if ($manifest['migrations'] !== $this->migrations()) {
            throw new RuntimeException('The backup was made with a different database version. Run the same migrations first (php artisan migrate), then restore.');
        }

        $safety = $this->create('pre-restore-');

        $zip = $this->open($path);
        $rows = 0;

        try {
            // 1. Database.
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($zip, $manifest, &$rows) {
                    foreach (array_keys($manifest['tables']) as $table) {
                        if (! Schema::hasTable($table)) {
                            throw new RuntimeException("Table {$table} does not exist in this database.");
                        }
                        DB::table($table)->delete();
                    }

                    foreach (array_keys($manifest['tables']) as $table) {
                        $stream = $zip->getStream("database/{$table}.jsonl");
                        $batch = [];

                        while (($line = fgets($stream)) !== false) {
                            if (trim($line) === '') {
                                continue;
                            }
                            $batch[] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                            if (count($batch) === 200) {
                                DB::table($table)->insert($batch);
                                $rows += count($batch);
                                $batch = [];
                            }
                        }
                        if ($batch) {
                            DB::table($table)->insert($batch);
                            $rows += count($batch);
                        }
                        fclose($stream);
                    }
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            // 2. Documents: replace the whole documents store.
            $disk = Storage::disk(ApplicationService::DISK);
            foreach ($disk->directories() as $directory) {
                $disk->deleteDirectory($directory);
            }
            $disk->delete($disk->files());

            foreach (array_keys($manifest['documents']) as $file) {
                $this->assertSafePath($file);
                $disk->writeStream($file, $zip->getStream('documents/'.$file));
            }
        } finally {
            $zip->close();
        }

        AuditLog::record('backup.restored', null, 'Restored the database and documents from a backup.', [
            'name' => basename($path),
            'backup_created_at' => $manifest['created_at'],
            'safety_backup' => $safety['name'],
            'rows' => $rows,
            'documents' => count($manifest['documents']),
        ]);

        return ['safety_backup' => $safety['name'], 'rows' => $rows, 'documents' => count($manifest['documents'])];
    }

    /**
     * @return list<array{name: string, path: string, size: int, created_at: Carbon}>
     */
    public function list(): array
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.zip'))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                // Carbon 3 reads timestamps as UTC unless told otherwise.
                'created_at' => Carbon::createFromTimestamp($file->getMTime(), config('app.timezone')),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function latest(): ?array
    {
        return collect($this->list())->first(fn ($backup) => str_starts_with($backup['name'], self::PREFIX));
    }

    /**
     * Accepts a file name inside the backup folder, or a full path.
     */
    public function resolve(string $file): string
    {
        $candidate = is_file($file) ? $file : $this->directory().DIRECTORY_SEPARATOR.basename($file);

        if (! is_file($candidate)) {
            throw new RuntimeException("Backup not found: {$file}");
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        // Only this application's database: a shared MySQL/MariaDB server may
        // hold other databases that must never end up in this backup.
        return collect(Schema::getTables(Schema::getCurrentSchemaListing()))
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, self::SKIP_TABLES, true) || str_starts_with($name, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function migrations(): array
    {
        return DB::table('migrations')->orderBy('migration')->pluck('migration')->all();
    }

    /** Keep only the newest regular backups (safety backups are kept). */
    private function prune(): void
    {
        $keep = max(1, (int) config('ucurec.backup.keep'));

        collect($this->list())
            ->filter(fn ($backup) => str_starts_with($backup['name'], self::PREFIX))
            ->slice($keep)
            ->each(fn ($backup) => @unlink($backup['path']));
    }

    private function add(ZipArchive $zip, string $source, string $entry): void
    {
        $zip->addFile($source, $entry);
        $this->encrypt($zip, $entry);
    }

    private function encrypt(ZipArchive $zip, string $entry): void
    {
        if ($password = $this->password()) {
            $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password);
        }
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException("Cannot open the backup: {$path}");
        }
        if ($password = $this->password()) {
            $zip->setPassword($password);
        }

        return $zip;
    }

    private function assertEntry(ZipArchive $zip, string $entry, string $expected): void
    {
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException("The backup is missing {$entry}.");
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        if (! hash_equals($expected, hash_final($context))) {
            throw new RuntimeException("Checksum mismatch for {$entry}: the backup is damaged or was changed.");
        }
    }

    /** Never write outside the documents folder (zip path traversal). */
    private function assertSafePath(string $file): void
    {
        if ($file === '' || str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, '\\') || str_contains($file, ':')) {
            throw new RuntimeException("Unsafe file path in backup: {$file}");
        }
    }

    private function directory(): string
    {
        return rtrim((string) config('ucurec.backup.path'), '\\/');
    }

    private function password(): ?string
    {
        $password = config('ucurec.backup.password');

        return is_string($password) && $password !== '' ? $password : null;
    }
}
