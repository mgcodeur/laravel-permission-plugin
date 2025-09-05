<?php

namespace Mgcodeur\LaravelPermissionPlugin;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class PermissionMigrationPlugin
{
    private const ACTION_ADD = 'add';

    private const ACTION_REVOKE = 'revoke';

    private const ACTION_DELETE = 'delete';

    /** @var array{add:string[],revoke:string[],delete:string[]} */
    protected array $actions = [
        self::ACTION_ADD => [],
        self::ACTION_REVOKE => [],
        self::ACTION_DELETE => [],
    ];

    protected ?string $guard = null;

    /** @var string[] */
    protected array $resources = [];

    /** @var string[] */
    protected array $roles = [];

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
        $this->pushActions(self::ACTION_ADD, $permissions);

        return $this;
    }

    protected function revokePermissions(string|array ...$permissions): static
    {
        $this->pushActions(self::ACTION_REVOKE, $permissions);

        return $this;
    }

    protected function deletePermissions(string|array ...$permissions): static
    {
        $this->pushActions(self::ACTION_DELETE, $permissions);

        return $this;
    }

    protected function for(string|array ...$roles): static
    {
        $this->roles = collect($roles)->flatten()->map(fn ($r) => (string) $r)->values()->all();

        return $this;
    }

    protected function execute(): void
    {
        if (empty($this->roles) && empty($this->actions[self::ACTION_DELETE])) {
            $this->resetState();

            return;
        }

        $permissionModel = config('permission.models.permission');
        $roleModel = config('permission.models.role');
        $guard = $this->guard ?? config('permission.defaults.guard', 'web');
        $resources = array_values(array_unique($this->resources));
        $roles = $this->roles;

        DB::transaction(function () use ($permissionModel, $roleModel, $guard, $resources, $roles) {
            $roleModels = $this->resolveRoles($roleModel, $roles, $guard);

            $this->runAction(self::ACTION_ADD, $permissionModel, $guard, $resources, function ($perm) use ($roleModels) {
                foreach ($roleModels as $role) {
                    $role->givePermissionTo($perm);
                }
            }, createIfMissing: true);

            $this->runAction(self::ACTION_REVOKE, $permissionModel, $guard, $resources, function ($perm) use ($roleModels) {
                foreach ($roleModels as $role) {
                    $role->revokePermissionTo($perm);
                }
            });

            $this->runAction(self::ACTION_DELETE, $permissionModel, $guard, $resources, function ($perm) {
                $perm->delete();
            });
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->resetState();
    }

    private function pushActions(string $type, array $items): void
    {
        foreach ($items as $item) {
            foreach ((array) $item as $name) {
                $this->actions[$type][] = (string) $name;
            }
        }
    }

    /**
     * @param  callable  $callback  function($permission): void
     */
    private function runAction(
        string $type,
        string $permissionModel,
        string $guard,
        array $resources,
        callable $callback,
        bool $createIfMissing = false
    ): void {
        foreach ($this->actions[$type] as $name) {
            $this->withTargets(
                permissionModel: $permissionModel,
                name: $name,
                guard: $guard,
                resources: $resources,
                createIfMissing: $createIfMissing,
                callback: $callback
            );
        }
    }

    /**
     * @param  callable  $callback  function($permission): void
     */
    private function withTargets(
        string $permissionModel,
        string $name,
        string $guard,
        array $resources,
        bool $createIfMissing,
        callable $callback
    ): void {
        if (! empty($resources)) {
            foreach ($resources as $res) {
                $perm = $this->findPermission($permissionModel, $name, $guard, $res);

                if (! $perm && $createIfMissing) {
                    $perm = $permissionModel::create([
                        'name' => $name,
                        'guard_name' => $guard,
                        'resource' => $res,
                    ]);
                }

                if ($perm) {
                    $callback($perm);
                }
            }

            return;
        }

        $perm = $this->findPermission($permissionModel, $name, $guard);

        if (! $perm && $createIfMissing) {
            $perm = method_exists($permissionModel, 'findOrCreate')
                ? $permissionModel::findOrCreate($name, $guard)
                : $permissionModel::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        if ($perm) {
            $callback($perm);
        }
    }

    private function findPermission(string $permissionModel, string $name, string $guard, ?string $resource = null): ?Permission
    {
        $query = $permissionModel::where('name', $name)->where('guard_name', $guard);
        if ($resource !== null) {
            $query->where('resource', $resource);
        }

        return $query->first();
    }

    /**
     * @return \Illuminate\Support\Collection<string,Role>
     */
    private function resolveRoles(string $roleModel, array $roles, string $guard)
    {
        if (empty($roles)) {
            return collect();
        }

        return collect($roles)->mapWithKeys(function ($roleName) use ($roleModel, $guard) {
            $role = method_exists($roleModel, 'findOrCreate')
                ? $roleModel::findOrCreate((string) $roleName, $guard)
                : $roleModel::firstOrCreate(['name' => (string) $roleName, 'guard_name' => $guard]);

            return [$role->name => $role];
        });
    }

    private function resetState(): void
    {
        $this->actions = [
            self::ACTION_ADD => [],
            self::ACTION_REVOKE => [],
            self::ACTION_DELETE => [],
        ];
        $this->resources = [];
        $this->roles = [];
    }
}
