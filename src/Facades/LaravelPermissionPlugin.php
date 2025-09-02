<?php

namespace Mgcodeur\LaravelPermissionPlugin\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mgcodeur\LaravelPermissionPlugin\LaravelPermissionPlugin
 */
class LaravelPermissionPlugin extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mgcodeur\LaravelPermissionPlugin\LaravelPermissionPlugin::class;
    }
}
