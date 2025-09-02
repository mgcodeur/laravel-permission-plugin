<?php

namespace Mgcodeur\LaravelPermissionPlugin;

use Mgcodeur\LaravelPermissionPlugin\Commands\LaravelPermissionPluginCommand;
use Mgcodeur\LaravelPermissionPlugin\Commands\MakePermissionPluginCommand;
use Mgcodeur\LaravelPermissionPlugin\Commands\MigratePermissionPluginCommand;
use Mgcodeur\LaravelPermissionPlugin\Commands\RollbackPermissionPluginCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPermissionPluginServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-permission-plugin')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_permission_migrations_table')
            ->hasCommands([
                LaravelPermissionPluginCommand::class,
                MakePermissionPluginCommand::class,
                MigratePermissionPluginCommand::class,
                RollbackPermissionPluginCommand::class,
            ]);
    }
}
