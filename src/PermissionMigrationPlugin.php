<?php

namespace Mgcodeur\LaravelPermissionPlugin;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

abstract class PermissionMigrationPlugin
{
    protected array $actions = [
        'add' => [],
        'revoke' => [],
        'delete' => [],
    ];

    protected ?string $guard = null;

    protected array $resources = [];

    abstract public function up(): void;

    abstract public function down(): void;

    public function guard(?string $guard): static
    {
        $this->guard = $guard;

        return $this;
    }

    public function setResource(string|array ...$resources): static
    {
        foreach ($resources as $item) {
            foreach ((array) $item as $res) {
                $this->resources[] = (string) $res;
            }
        }

        return $this;
    }

    public function getResources(): array
    {
        return $this->resources;
    }

    protected function givePermissions(string|array ...$permissions): static
    {
        foreach ($permissions as $item) {
            foreach ((array) $item as $name) {
                $this->actions['add'][] = (string) $name;
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
        $resources = array_values(array_unique($this->resources));

        DB::transaction(function () use ($permissionModel, $roleModel, $guard, $roles, $resources) {
            $roleModels = collect($roles)->mapWithKeys(function ($roleName) use ($roleModel, $guard) {
                $role = method_exists($roleModel, 'findOrCreate')
                    ? $roleModel::findOrCreate((string) $roleName, $guard)
                    : $roleModel::firstOrCreate(['name' => (string) $roleName, 'guard_name' => $guard]);

                return [$role->name => $role];
            });

            // ADD
            foreach ($this->actions['add'] as $name) {
                if (! empty($resources)) {
                    foreach ($resources as $res) {
                        $perm = $permissionModel::firstOrCreate([
                            'name' => $name,
                            'guard_name' => $guard,
                            'resource' => $res,
                        ]);

                        foreach ($roleModels as $role) {
                            $role->givePermissionTo($perm);
                        }
                    }
                } else {
                    $perm = method_exists($permissionModel, 'findOrCreate')
                        ? $permissionModel::findOrCreate($name, $guard)
                        : $permissionModel::firstOrCreate(['name' => $name, 'guard_name' => $guard]);

                    foreach ($roleModels as $role) {
                        $role->givePermissionTo($perm);
                    }
                }
            }

            // REVOKE
            foreach ($this->actions['revoke'] as $name) {
                if (! empty($resources)) {
                    foreach ($resources as $res) {
                        $perm = $permissionModel::where('name', $name)
                            ->where('guard_name', $guard)
                            ->where('resource', $res)
                            ->first();

                        if ($perm) {
                            foreach ($roleModels as $role) {
                                $role->revokePermissionTo($perm);
                            }
                        }
                    }
                } else {
                    $perm = $permissionModel::where('name', $name)
                        ->where('guard_name', $guard)
                        ->first();

                    if ($perm) {
                        foreach ($roleModels as $role) {
                            $role->revokePermissionTo($perm);
                        }
                    }
                }
            }

            // DELETE
            foreach ($this->actions['delete'] as $name) {
                if (! empty($resources)) {
                    foreach ($resources as $res) {
                        $perm = $permissionModel::where('name', $name)
                            ->where('guard_name', $guard)
                            ->where('resource', $res)
                            ->first();

                        if ($perm) {
                            $perm->delete();
                        }
                    }
                } else {
                    $perm = $permissionModel::where('name', $name)
                        ->where('guard_name', $guard)
                        ->first();

                    if ($perm) {
                        $perm->delete();
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actions = ['add' => [], 'revoke' => [], 'delete' => []];
        $this->resources = [];
    }
}
