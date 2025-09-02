<?php

namespace Mgcodeur\LaravelPermissionPlugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Mgcodeur\LaravelPermissionPlugin\Repositories\PermissionMigrationRepository;

class RollbackPermissionPluginCommand extends Command
{
    protected $signature = 'permission:rollback 
        {--steps= : Number of files to rollback} 
        {--path=}';

    protected $description = 'Rollback permission migrations using last batch by default, or steps when provided.';

    public function handle(Filesystem $files, PermissionMigrationRepository $repo): int
    {
        $path = $this->option('path') ?: base_path('database/permissions');

        if (! $files->isDirectory($path)) {
            $this->info('No permission directory found.');

            return self::SUCCESS;
        }

        $stepsOpt = $this->option('steps');

        $targets = [];

        if ($stepsOpt !== null && trim((string) $stepsOpt) !== '') {
            $n = max(1, (int) $stepsOpt);
            $targets = $repo->getLastN($n);
            if ($targets === []) {
                $this->info('Nothing to rollback (no entries for given steps).');

                return self::SUCCESS;
            }
        } else {
            $lastBatch = $repo->getLastBatch();
            if ($lastBatch === []) {
                $this->info('Nothing to rollback (no batch found).');

                return self::SUCCESS;
            }
            $targets = array_map(fn ($batch) => $batch['filename'], $lastBatch);
        }

        foreach ($targets as $filename) {
            $filePath = $path.DIRECTORY_SEPARATOR.$filename;
            $this->line('Rolling back: '.$filename);

            if (! file_exists($filePath)) {
                $this->warn("File missing. Removing log only: {$filename}");
                $repo->deleteLog($filename);

                continue;
            }

            $migration = require $filePath;
            if (is_object($migration) && method_exists($migration, 'down')) {
                $migration->down();
            } else {
                $this->warn("Skipped (no down()): {$filename}");
            }

            $repo->deleteLog($filename);
        }

        $this->info('permissions rollback complete.');

        return self::SUCCESS;
    }
}
