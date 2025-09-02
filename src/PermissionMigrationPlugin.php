<?php

namespace Mgcodeur\LaravelPermissionPlugin;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

abstract class PermissionMigrationPlugin
{
    protected array $actions = [
        'create' => [],
        'revoke' => [],
        'delete' => [],
    ];

    protected ?string $guard = null;

    abstract public function up(): void;

    abstract public function down(): void;

    public function guard(?string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    protected function createPermissions(string|array ...$permissions): static
    {
        foreach ($permissions as $item) {
            foreach ((array) $item as $name) {
                $this->actions['create'][] = (string) $name;
            }
        }

        return $this;
    }

    protected function revokePermissions(string|array ...$permissions): static
    {
        foreach ($permissions as $item) {
            foreach ((array) $item as $name) {
                $this->actions['revoke'][] = (string) $name;
            }
        }

        return $this;
    }

    protected function deletePermissions(string|array ...$permissions): static
    {
        foreach ($permissions as $item) {
            foreach ((array) $item as $name) {
                $this->actions['delete'][] = (string) $name;
            }
        }

        return $this;
    }

    protected function for(string|array ...$roles): void
    {
        $roles = collect($roles)->flatten()->all();

        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');
        $guard = $this->guard ?? config('permission.defaults.guard', 'web');

        DB::transaction(function () use ($permissionModel, $roleModel, $guard, $roles) {
            $roleModels = collect($roles)->mapWithKeys(function ($roleName) use ($roleModel, $guard) {
                $role = method_exists($roleModel, 'findOrCreate')
                    ? $roleModel::findOrCreate((string) $roleName, $guard)
                    : $roleModel::firstOrCreate(['name' => (string) $roleName, 'guard_name' => $guard]);

                return [$role->name => $role];
            });

            foreach ($this->actions['create'] as $name) {
                $perm = method_exists($permissionModel, 'findOrCreate')
                    ? $permissionModel::findOrCreate($name, $guard)
                    : $permissionModel::firstOrCreate(['name' => $name, 'guard_name' => $guard]);

                foreach ($roleModels as $role) {
                    $role->givePermissionTo($perm);
                }
            }

            foreach ($this->actions['revoke'] as $name) {
                $perm = $permissionModel::where('name', $name)->where('guard_name', $guard)->first();
                if ($perm) {
                    foreach ($roleModels as $role) {
                        $role->revokePermissionTo($perm);
                    }
                }
            }

            foreach ($this->actions['delete'] as $name) {
                $perm = $permissionModel::where('name', $name)->where('guard_name', $guard)->first();
                if ($perm) {
                    $perm->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actions = ['create' => [], 'revoke' => [], 'delete' => []];
    }
}
