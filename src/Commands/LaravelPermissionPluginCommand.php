<?php

namespace Mgcodeur\LaravelPermissionPlugin\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class LaravelPermissionPluginCommand extends Command
{
    public $signature = 'laravel-permission-plugin:install';

    public $description = 'Install the Laravel Permission Plugin package';

    public function handle(): int
    {
        $this->comment('All done');

        Artisan::call('vendor:publish', [
            '--tag' => 'permission-plugin-migrations',
        ]);

        return self::SUCCESS;
    }
}
