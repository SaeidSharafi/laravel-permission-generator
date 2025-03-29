<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use SaeidSharafi\LaravelPermissionGenerator\Commands\GeneratePermissionsEnum;
use SaeidSharafi\LaravelPermissionGenerator\Enums\PermissionAction;

beforeEach(function () {
    $this->outputPath = base_path('app/Enums/PermissionEnum.php');

    if (!File::isDirectory(dirname($this->outputPath))) {
        File::makeDirectory(dirname($this->outputPath), 0755, true);
    }

    Config::set('permission-generator.output_enum', $this->outputPath);
    Config::set('permission-generator.resources', [
        'user' => [PermissionAction::VIEW, PermissionAction::CREATE, PermissionAction::UPDATE, PermissionAction::DELETE, PermissionAction::VIEW_SCOPED],
        'post' => ['view', 'create', 'update', 'delete', 'publish']
    ]);
    Config::set('permission-generator.custom_permissions', [
        'admin_dashboard' => 'dashboard.access',
        'manage_settings' => 'settings.manage'
    ]);

    if (File::exists($this->outputPath)) {
        File::delete($this->outputPath);
    }
});

afterEach(function () {
    if (File::exists($this->outputPath)) {
        File::delete($this->outputPath);
    }
});

test('command generates enum file successfully', function () {
    $this->artisan('permissions:generate-enum', ['--force' => true])
         ->expectsOutput("Successfully generated PermissionEnum at [{$this->outputPath}].")
         ->assertExitCode(0);

    expect(File::exists($this->outputPath))->toBeTrue();
    $content = File::get($this->outputPath);

    expect($content)->toContain('enum PermissionEnum: string');
    expect($content)->toContain("case USER_VIEW = 'users.view';");
    expect($content)->toContain("case USER_VIEW_ANY = 'users.view_any';");
    expect($content)->toContain("case POST_PUBLISH = 'posts.publish';");
    expect($content)->toContain("case ADMIN_DASHBOARD = 'dashboard.access';");
    expect($content)->toContain("public static function getAllValues(): array");
    expect($content)->toContain("public static function getAllNames(): array");
});

test('command shows error when config is missing', function () {
    Config::set('permission-generator', null);
    $this->artisan('permissions:generate-enum')
         ->expectsOutput('Configuration file config/permission-generator.php not found, empty, or not published.')
         ->assertExitCode(1);
});

test('command shows error when output path is missing', function () {
    Config::set('permission-generator.output_enum', null);
    $this->artisan('permissions:generate-enum')
         ->expectsOutput("The 'output_enum' path is not defined in config/permission-generator.php.")
         ->assertExitCode(1);
});

test('command prompts for confirmation when file exists', function () {
    File::put($this->outputPath, 'test content');

    $this->artisan('permissions:generate-enum')
         ->expectsQuestion("The file [{$this->outputPath}] already exists. Do you want to overwrite it?", false)
         ->expectsOutput('Enum generation aborted.')
         ->assertExitCode(0);

    expect(File::get($this->outputPath))->toBe('test content');
});

test('enum generation handles empty permissions gracefully', function () {
    Config::set('permission-generator.resources', []);
    Config::set('permission-generator.custom_permissions', []);

    $this->artisan('permissions:generate-enum', ['--force' => true])
         ->expectsOutput('No permissions were generated. Check your configuration.')
         ->assertExitCode(0);

    expect(File::exists($this->outputPath))->toBeTrue();
    $content = File::get($this->outputPath);

    expect($content)->toContain('// No permissions defined or generated from config/permission-generator.php');
});
