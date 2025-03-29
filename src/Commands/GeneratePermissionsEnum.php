<?php

namespace SaeidSharafi\LaravelPermissionGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class GeneratePermissionsEnum extends Command
{
    protected $signature = 'permissions:generate-enum {--force : Overwrite existing Enum file}';
    protected $description = 'Generate the PermissionEnum class from the config/permission-generator.php file.';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $config = config('permission-generator');
        if (!$this->validateConfiguration($config)) {
            return Command::FAILURE;
        }

        $outputPath = config('permission-generator.output_enum');

        if (!$this->prepareOutputDirectory($outputPath)) {
            return Command::FAILURE;
        }

        if (!$this->option('force') && $this->files->exists($outputPath)) {
            if (!$this->confirm("The file [{$outputPath}] already exists. Do you want to overwrite it?")) {
                $this->info('Enum generation aborted.');
                return Command::SUCCESS;
            }
        }

        $this->info("Generating permissions based on 'config/permission-generator.php'...");

        $enumCases = $this->generateEnumCases($config);
        if (empty($enumCases)) {
            $this->warn('No permissions were generated. Check your configuration.');
        }

        $stub = $this->generateEnumStub($enumCases, $outputPath);

        try {
            $this->files->put($outputPath, $stub);
        } catch (\Exception $e) {
            $this->error("Could not write to file [{$outputPath}]: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("Successfully generated PermissionEnum at [{$outputPath}].");
        $this->warn("Remember to run 'php artisan permissions:sync' to update the database!");

        return Command::SUCCESS;
    }

    private function validateConfiguration(?array $config): bool
    {
        if (!$config) {
            $this->error('Configuration file config/permission-generator.php not found, empty, or not published.');
            $this->line('Please publish the configuration using: php artisan vendor:publish --provider="SaeidSharafi\LaravelPermissionGenerator\PermissionGeneratorServiceProvider" --tag="permission-generator-config"');
            return false;
        }

        if (!config('permission-generator.output_enum')) {
            $this->error("The 'output_enum' path is not defined in config/permission-generator.php.");
            return false;
        }

        return true;
    }

    private function prepareOutputDirectory(string $outputPath): bool
    {
        $outputDirectory = dirname($outputPath);

        if (!$this->files->isDirectory($outputDirectory)) {
            if (!$this->files->makeDirectory($outputDirectory, 0755, true, true)) {
                $this->error("Could not create directory: {$outputDirectory}");
                return false;
            }
        }

        return true;
    }

    protected function generateEnumCases(array $config): array
    {
        $cases = [];
        $resources = $config['resources'] ?? [];
        $customPermissions = $config['custom_permissions'] ?? [];

        $cases = array_merge($cases, $this->processResourcePermissions($resources));

        foreach ($customPermissions as $caseName => $permissionString) {
            $cases[Str::upper(Str::snake($caseName))] = $permissionString;
        }

        ksort($cases);
        return $cases;
    }

    private function processResourcePermissions(array $resources): array
    {
        $cases = [];

        foreach ($resources as $resource => $actions) {
            $resourceUpper = Str::upper(Str::snake($resource));
            $resourceSlug = Str::plural(Str::snake($resource));

            foreach ($actions as $action) {
                $actionValue = $this->getActionValue($action, $resource);
                if (!$actionValue) continue;

                $actionUpper = Str::upper(Str::snake($actionValue));

                if (str_ends_with($actionValue, '_scoped')) {
                    $baseActionValue = str_replace('_scoped', '', $actionValue);
                    $baseActionUpper = Str::upper(Str::snake($baseActionValue));

                    $cases[$resourceUpper . '_' . $baseActionUpper . '_ANY'] = $resourceSlug . '.' . $baseActionValue . '_any';
                    $cases[$resourceUpper . '_' . $baseActionUpper] = $resourceSlug . '.' . $baseActionValue;
                } else {
                    $cases[$resourceUpper . '_' . $actionUpper] = $resourceSlug . '.' . $actionValue;
                }
            }
        }

        return $cases;
    }

    private function getActionValue($action, string $resource): ?string
    {
        if ($action instanceof \BackedEnum) {
            return $action->value;
        }

        if (is_string($action)) {
            return empty($action) ? null : $action;
        }

        $this->warn("Skipping invalid action type for resource '{$resource}': " . gettype($action));
        return null;
    }

    protected function generateEnumStub(array $enumCases, string $outputPath): string
    {
        $namespace = $this->deriveNamespace($outputPath);
        $className = Str::before(basename($outputPath), '.php');

        $casesString = $this->formatEnumCases($enumCases);

        $customStubPath = $this->laravel->basePath('stubs/vendor/permission-generator/enum.stub');
        $packageStubPath = __DIR__ . '/../stubs/enum.stub';

        $stubPath = file_exists($customStubPath) ? $customStubPath : $packageStubPath;

        if (!file_exists($stubPath)) {
            $this->error("Stub file not found at: {$stubPath}");
            return '';
        }

        $stub = file_get_contents($stubPath);

        $stub = str_replace('{{ namespace }}', $namespace, $stub);
        $stub = str_replace('{{ class }}', $className, $stub);
        $stub = str_replace('{{ cases }}', $casesString, $stub);

        return $stub;
    }

    private function deriveNamespace(string $outputPath): string
    {
        $appNamespace = $this->laravel->getNamespace();
        $appBasePath = $this->laravel->path();

        $relativeEnumPath = Str::after($outputPath, $appBasePath . DIRECTORY_SEPARATOR);

        $enumNamespaceSuffix = str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            Str::beforeLast($relativeEnumPath, DIRECTORY_SEPARATOR)
        );

        $enumNamespaceSuffix = $enumNamespaceSuffix ? '\\' . $enumNamespaceSuffix : '';

        return rtrim($appNamespace, '\\') . $enumNamespaceSuffix;
    }

    private function formatEnumCases(array $enumCases): string
    {
        if (empty($enumCases)) {
            return "    // No permissions defined or generated from config/permission-generator.php\n";
        }

        $casesString = '';

        foreach ($enumCases as $caseName => $permissionString) {
            $caseName = preg_replace('/[^A-Z0-9_]/', '', $caseName);

            if (empty($caseName)) {
                continue;
            }

            if (is_numeric(substr($caseName, 0, 1))) {
                $this->warn("Skipping potentially invalid Enum case name starting with a number: {$caseName}");
                continue;
            }

            $casesString .= "    case {$caseName} = '{$permissionString}';\n";
        }

        return empty(trim($casesString))
            ? "    // No permissions defined or generated from config/permission-generator.php\n"
            : $casesString;
    }
}
