<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupVerifyCommand extends Command
{
    protected $signature = 'ucurec:backup-verify {file : Backup file name in BACKUP_PATH, or a full path}';

    protected $description = 'Check a backup against its checksums without changing anything';

    public function handle(BackupService $backups): int
    {
        try {
            $manifest = $backups->verify($backups->resolve($this->argument('file')));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = array_sum(array_column($manifest['tables'], 'rows'));
        $this->components->twoColumnDetail('Created', $manifest['created_at']);
        $this->components->twoColumnDetail('Tables / rows', count($manifest['tables'])." / {$rows}");
        $this->components->twoColumnDetail('Documents', (string) count($manifest['documents']));
        $this->components->info('All checksums match. The backup is readable.');

        return self::SUCCESS;
    }
}
