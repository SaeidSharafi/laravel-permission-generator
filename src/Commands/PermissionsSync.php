<?php

namespace SaeidSharafi\LaravelPermissionGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use function Laravel\Prompts\select;

class PermissionsSync extends Command
{
    protected $signature = 'permissions:sync {--guard= : authentication guard} {--fresh : Delete existing permissions before syncing} {--Y|yes : Skip confirmation prompts}';
    protected $description = 'Sync permissions from the generated Enum to the database.';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(PermissionRegistrar $permissionRegistrar): int
    {
        $this->info('Starting permission sync...');

        $enumClass = config('permission-generator.enum_class');
        $enumPath = config('permission-generator.output_enum');

        if (!$enumClass || !$enumPath) {
            $this->error("The Enum class or path is not configured correctly.");
            return Command::FAILURE;
        }

        if (app()->environment('testing')) {
            $enumClass = 'App\\Enums\\PermissionEnum';
            if (file_exists($enumPath)) {
                require_once $enumPath;
            }
        }

        if (!$this->files->exists($enumPath)) {
            $this->warn("The Enum file does not exist at [{$enumPath}].");
            $this->info("Running 'permissions:generate-enum' command to create it...");

            $exitCode = $this->call('permissions:generate-enum', ['--force' => true]);

            if ($exitCode !== Command::SUCCESS) {
                $this->error("Failed to generate the Enum file. Please run 'php artisan permissions:generate-enum' manually.");
                return Command::FAILURE;
            }

            if (app()->environment('testing') && file_exists($enumPath)) {
                require_once $enumPath;
            }
        }

        if (!class_exists($enumClass) || !method_exists($enumClass, 'getAllValues')) {
            $this->error("The Enum class '{$enumClass}' does not exist or is missing required methods.");
            return Command::FAILURE;
        }

        try {
            $definedPermissions = $enumClass::getAllValues();
            if (empty($definedPermissions)) {
                $this->warn('No permissions found in the Enum class. Sync aborted.');
                return Command::SUCCESS;
            }
        } catch (\Throwable $e) {
            $this->error("Could not load permissions from Enum class '{$enumClass}': " . $e->getMessage());
            return Command::FAILURE;
        }

        $permissionTableName = config('permission.table_names.permissions');
        $rolePermissionsTableName = config('permission.table_names.role_has_permissions');
        $modelPermissionsTableName = config('permission.table_names.model_has_permissions');

        if ($this->option('fresh') && $this->confirmDelete()) {
            if (!$this->deleteExistingPermissions($permissionTableName, $rolePermissionsTableName, $modelPermissionsTableName, $permissionRegistrar)) {
                return Command::FAILURE;
            }
        }

        if (!($guard = $this->option('guard'))) {
            $guards = array_keys(config('auth.guards', ['web']));
            $guard = select('Select guard for permissions', $guards, 0);

            if (!in_array($guard, $guards)) {
                $this->error("Guard '{$guard}' not found in the configuration.");
                return Command::FAILURE;
            }
        }

        [$createdCount, $existingCount] = $this->syncPermissions($definedPermissions, $guard);
        $this->info("Sync complete. Found {$existingCount} existing permissions. Created {$createdCount} new permissions.");

        if ($createdCount > 0) {
            $permissionRegistrar->forgetCachedPermissions();
        }

        $superAdminRoleName = config('permission-generator.super_admin_role');
        if ($superAdminRoleName && $createdCount > 0) {
            $this->syncSuperAdminRole($superAdminRoleName, $guard);
        }

        if (config('permission-generator.remove_stale_permissions', false)) {
            $this->removeStalePermissions($definedPermissions, $guard);
        }

        return Command::SUCCESS;
    }

    private function confirmDelete(): bool
    {
        $confirmMessage = 'Are you sure you want to delete ALL permissions? This affects all roles and users and cannot be undone.';

        if ($this->option('yes') || $this->confirm($confirmMessage, false)) {
            return true;
        }

        $this->info('Fresh sync aborted.');
        return false;
    }

    private function deleteExistingPermissions(string $permissionTableName, string $rolePermissionsTableName, string $modelPermissionsTableName, PermissionRegistrar $permissionRegistrar): bool
    {
        $this->warn('Starting fresh sync: Deleting existing permissions and role/model associations!');

        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            $this->toggleForeignKeyChecks($driver, false);

            DB::table($rolePermissionsTableName)->delete();
            DB::table($modelPermissionsTableName)->delete();
            DB::table($permissionTableName)->delete();

            $this->toggleForeignKeyChecks($driver, true);

            $this->info('Existing permissions and associations deleted.');
            $permissionRegistrar->forgetCachedPermissions();
            return true;
        } catch (\Exception $e) {
            $this->toggleForeignKeyChecks(DB::connection()->getDriverName(), true);

            $this->error('Error deleting permissions: ' . $e->getMessage());
            return false;
        }
    }

    private function toggleForeignKeyChecks(string $driver, bool $enable): void
    {
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=' . ($enable ? '1' : '0'));
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ' . ($enable ? 'ON' : 'OFF'));
        } elseif ($driver === 'pgsql') {
            if ($enable) {
                DB::commit();
            } else {
                DB::beginTransaction();
                DB::statement('SET CONSTRAINTS ALL DEFERRED');
            }
        }
    }

    private function syncPermissions(array $definedPermissions , string $guard): array
    {
        $guardName = $guard ?: config('auth.defaults.guard', 'web');
        $createdCount = 0;
        $existingCount = 0;

        $this->line("Using guard: {$guardName}");

        foreach ($definedPermissions as $permissionName) {
            if (empty($permissionName)) {
                continue;
            }

            try {
                $permission = Permission::findOrCreate($permissionName, $guardName);

                if ($permission->wasRecentlyCreated) {
                    $this->line("- Created: {$permissionName}");
                    $createdCount++;
                } else {
                    $existingCount++;
                }
            } catch (\Exception $e) {
                $this->error("Error processing permission '{$permissionName}': " . $e->getMessage());
            }
        }

        return [$createdCount, $existingCount];
    }

    private function syncSuperAdminRole(string $roleName, string $guardName): void
    {
        try {
            $superAdminRole = Role::where('name', $roleName)->where('guard_name', $guardName)->first();
            if ($superAdminRole) {
                $this->line("Syncing all permissions to '{$roleName}' role...");
                $allPermissions = Permission::where('guard_name', $guardName)->pluck('name');
                $superAdminRole->syncPermissions($allPermissions);
                $this->info("Role '{$roleName}' synced with all permissions.");
            } else {
                $this->warn("Super Admin role '{$roleName}' with guard '{$guardName}' not found. Skipping auto-sync for this role.");
            }
        } catch (\Exception $e) {
            $this->error("Error syncing super-admin role '{$roleName}': ".$e->getMessage());
        }
    }

    private function removeStalePermissions(array $definedPermissions, string $guardName): void
    {
        $this->line('Checking for stale permissions to remove...');
        try {
            $stalePermissions = Permission::where('guard_name', $guardName)
                ->whereNotIn('name', $definedPermissions)
                ->get();

            if ($stalePermissions->isNotEmpty()) {
                $staleNames = $stalePermissions->pluck('name')->implode(', ');
                $confirmMessage = "Found stale permissions in DB: [{$staleNames}]. Remove them? (Associations will also be removed)";

                if ($this->option('yes') || $this->confirm($confirmMessage, false)) {
                    $deletedCount = 0;
                    foreach ($stalePermissions as $stale) {
                        try {
                            $stale->delete();
                            $this->line("- Deleted stale permission: {$stale->name}");
                            $deletedCount++;
                        } catch (\Exception $e) {
                            $this->error("Error deleting stale permission '{$stale->name}': ".$e->getMessage());
                        }
                    }
                    if ($deletedCount > 0) {
                        $this->laravel[PermissionRegistrar::class]->forgetCachedPermissions();
                        $this->info("{$deletedCount} stale permissions removed.");
                    } else {
                        $this->info("No stale permissions were removed due to errors.");
                    }
                } else {
                    $this->info('Stale permissions were not removed.');
                }
            } else {
                $this->info('No stale permissions found.');
            }
        } catch (\Exception $e) {
            $this->error("Error checking/removing stale permissions: ".$e->getMessage());
        }
    }
}
