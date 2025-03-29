<?php
use SaeidSharafi\LaravelPermissionGenerator\Commands\GeneratePermissionsEnum;
use SaeidSharafi\LaravelPermissionGenerator\Commands\PermissionsSync;
use SaeidSharafi\LaravelPermissionGenerator\PermissionGeneratorServiceProvider;

test('service provider registers commands', function () {
    $commands = $this->app->make('Illuminate\Contracts\Console\Kernel')->all();

    expect($commands)->toHaveKey('permissions:generate-enum')
        ->and($commands['permissions:generate-enum'])->toBeInstanceOf(GeneratePermissionsEnum::class)
        ->and($commands)->toHaveKey('permissions:sync')
        ->and($commands['permissions:sync'])->toBeInstanceOf(PermissionsSync::class);
});

test('service provider publishes config file', function () {
    $configPath = config_path('permission-generator.php');
    if (file_exists($configPath)) {
        unlink($configPath);
    }

    $this->artisan('vendor:publish', [
        '--provider' => PermissionGeneratorServiceProvider::class,
        '--tag' => 'permission-generator-config'
    ])->assertExitCode(0);

    expect(file_exists($configPath))->toBeTrue();
    $config = include $configPath;
    expect($config)->toBeArray()
        ->and($config)->toHaveKey('output_enum')
        ->and($config)->toHaveKey('resources')
        ->and($config)->toHaveKey('custom_permissions');
});

test('service provider merges config', function () {
    $config = config('permission-generator');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('output_enum')
        ->and($config)->toHaveKey('resources')
        ->and($config)->toHaveKey('custom_permissions');
});
