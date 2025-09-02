<?php

declare(strict_types=1);

namespace Mgcodeur\LaravelPermissionPlugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakePermissionPluginCommand extends Command
{
    protected $signature = 'make:permission {name : The name of the permission migration file} {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a new permission migration file in the database/permissions directory';

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');

        $path = base_path('database/permissions');
        if (! $files->isDirectory($path)) {
            $files->makeDirectory($path, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_".Str::snake($name).'.php';
        $fullPath = $path.DIRECTORY_SEPARATOR.$filename;

        if ($files->exists($fullPath) && ! $this->option('force')) {
            $this->error("File already exists: {$fullPath}");

            return self::FAILURE;
        }

        $stubPathCandidates = [
            __DIR__.'/../../stubs/permission.stub',
            base_path('stubs/permission.stub'),
        ];
        $stubPath = collect($stubPathCandidates)->first(fn ($p) => $files->exists($p));
        if (! $stubPath) {
            $this->error('permission.stub not found.');

            return self::FAILURE;
        }

        $stub = $files->get($stubPath);

        $className = Str::studly($name);
        $stub = str_replace(
            ['DummyClass', '{{ class }}'],
            [$className, $className],
            $stub
        );

        $files->put($fullPath, $stub);

        $this->info("Created: database/permissions/{$filename}");

        return self::SUCCESS;
    }
}
