<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreCommand extends Command
{
    protected $signature = 'ucurec:restore
        {file : Backup file name in BACKUP_PATH, or a full path}
        {--force : Do not ask for confirmation}';

    protected $description = 'Replace ALL records and documents with the contents of a backup (a safety backup is made first)';

    public function handle(BackupService $backups): int
    {
        try {
            $path = $backups->resolve($this->argument('file'));
            $manifest = $backups->verify($path);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->warn('This replaces every record and uploaded document with the backup from '.$manifest['created_at'].'.');

        if (! $this->option('force') && ! $this->confirm('Restore this backup now?')) {
            $this->components->info('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        try {
            $result = $backups->restore($path);
        } catch (Throwable $e) {
            $this->components->error('Restore failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Rows restored', (string) $result['rows']);
        $this->components->twoColumnDetail('Documents restored', (string) $result['documents']);
        $this->components->twoColumnDetail('Safety backup of the previous state', $result['safety_backup']);
        $this->components->info('Restore complete.');

        return self::SUCCESS;
    }
}
