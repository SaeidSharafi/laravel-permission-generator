<?php

namespace SaeidSharafi\LaravelPermissionGenerator;

use Illuminate\Support\ServiceProvider;
use SaeidSharafi\LaravelPermissionGenerator\Commands\GeneratePermissionsEnum;
use SaeidSharafi\LaravelPermissionGenerator\Commands\PermissionsSync;

class PermissionGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/permission-generator.php',
            'permission-generator'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GeneratePermissionsEnum::class,
                PermissionsSync::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/permission-generator.php' => $this->app->configPath('permission-generator.php'),
            ], 'permission-generator-config');

            $this->publishes([
                __DIR__.'/stubs' => $this->app->basePath('stubs/vendor/permission-generator'),
            ], 'permission-generator-stubs');
        }
    }
}
