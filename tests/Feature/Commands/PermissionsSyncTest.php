<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $enumPath = app_path('Enums/PermissionEnum.php');
    $enumDir = dirname($enumPath);
    if (!File::isDirectory($enumDir)) {
        File::makeDirectory($enumDir, 0755, true);
    }

    $this->enumPath = $enumPath;
    Config::set('permission-generator.output_enum', $this->enumPath);
    Config::set('permission-generator.enum_class', 'App\\Enums\\PermissionEnum');
    Config::set('permission-generator.super_admin_role', 'Super Admin');
    Config::set('permission-generator.remove_stale_permissions', true);

    $this->testPermissions = ['users.view', 'users.create', 'users.update', 'users.delete', 'dashboard.access'];

    Config::set('permission-generator.resources', [
        'users' => ['view', 'create', 'update', 'delete'],
    ]);

    Config::set('permission-generator.custom_permissions', [
        'ADMIN_DASHBOARD' => 'dashboard.access',
    ]);

    $enumContent = <<<'PHP'
<?php
namespace App\Enums;
enum PermissionEnum: string
{
    case USER_VIEW = 'users.view';
    case USER_CREATE = 'users.create';
    case USER_UPDATE = 'users.update';
    case USER_DELETE = 'users.delete';
    case ADMIN_DASHBOARD = 'dashboard.access';
    public static function getAllValues(): array
    {
        return array_column(self::cases(), 'value');
    }
    public static function getAllNames(): array
    {
        return array_column(self::cases(), 'name');
    }
}
PHP;
    File::put($this->enumPath, $enumContent);

    Permission::query()->delete();
});

afterEach(function () {
    if (File::exists($this->enumPath)) {
        File::delete($this->enumPath);
    }
    Permission::query()->delete();
    Mockery::close();
});

test('sync command creates permissions from enum and generates the enum file if missing', function () {
    if (File::exists($this->enumPath)) {
        File::delete($this->enumPath);
    }

    $this->withoutMockingConsoleOutput()
        ->artisan('permissions:sync', ['--verbose' => true]);

    $result = Artisan::output();

    expect(File::exists($this->enumPath))->toBeTrue();

    foreach ($this->testPermissions as $permName) {
        expect(Permission::where('name', $permName)->exists())->toBeTrue();
    }
});

test('sync command with fresh option deletes existing permissions', function () {
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'test.permission', 'guard_name' => 'web']);

    $this->artisan('permissions:sync', ['--fresh' => true, '--yes' => true])
         ->expectsOutput('Starting fresh sync: Deleting existing permissions and role/model associations!')
         ->expectsOutput('Existing permissions and associations deleted.')
         ->assertExitCode(0);

    foreach ($this->testPermissions as $permName) {
        expect(Permission::where('name', $permName)->exists())->toBeTrue();
    }
    expect(Permission::where('name', 'test.permission')->exists())->toBeFalse();
});

test('sync command syncs super admin role when enabled', function () {
    $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);

    $this->artisan('permissions:sync')
         ->expectsOutput("Syncing all permissions to 'Super Admin' role...")
         ->expectsOutput("Role 'Super Admin' synced with all permissions.")
         ->assertExitCode(0);

    $rolePermissions = $role->permissions()->pluck('name')->toArray();
    expect($rolePermissions)->toEqualCanonicalizing($this->testPermissions);
});

test('sync command removes stale permissions when enabled', function () {
    Permission::create(['name' => 'stale.permission', 'guard_name' => 'web']);

    $this->artisan('permissions:sync', ['--yes' => true])
         ->expectsOutput('Checking for stale permissions to remove...')
         ->assertExitCode(0);

    expect(Permission::where('name', 'stale.permission')->exists())->toBeFalse();

    foreach ($this->testPermissions as $permName) {
        expect(Permission::where('name', $permName)->exists())->toBeTrue();
    }
});

test('sync command creates enum file when missing', function () {
    File::delete($this->enumPath);

    $this->artisan('permissions:sync')
         ->expectsOutput("The Enum file does not exist at [{$this->enumPath}].")
         ->expectsOutput("Running 'permissions:generate-enum' command to create it...")
         ->assertExitCode(0);

    expect(File::exists($this->enumPath))->toBeTrue();
});
