# Laravel Permission Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/saeidsharafi/laravel-permission-generator.svg?style=flat-square)](https://packagist.org/packages/saeidsharafi/laravel-permission-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/saeidsharafi/laravel-permission-generator.svg?style=flat-square)](https://packagist.org/packages/saeidsharafi/laravel-permission-generator)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

Generate a PHP Permission Enum class for your Laravel application based on a configuration file and keep it synchronized with your `spatie/laravel-permission` database tables.

Stop manually defining permission strings everywhere and prevent typos!

## Features

*   Define permission structure (resources, actions, custom permissions) in a single config file.
*   Generate a PHP 8.1+ backed Enum class containing all your permissions.
*   Provides IDE auto-completion and type safety when referencing permissions.
*   Includes an Artisan command to sync the generated permissions with your database (using `spatie/laravel-permission`).
*   Handles common permission patterns (e.g., `view_scoped` string generates `resource.view_any` and `resource.view`).
*   Supports custom actions and standalone permissions defined via strings or custom Enums.
*   Optionally syncs all permissions to a designated "Super Admin" role.
*   Optionally cleans up stale permissions from the database.
*   Customizable enum template through published stubs.
*   Automatically generates the enum file if missing during permissions sync.
*   Configurable enum class namespace.

## Requirements

*   PHP >= 8.1
*   Laravel >= 9.0
*   `spatie/laravel-permission` >= 5.5

## Installation

Install the package via Composer:

```bash
composer require saeidsharafi/laravel-permission-generator
```

## Setup

1.  **Publish the configuration file:**
    ```bash
    php artisan vendor:publish --provider="SaeidSharafi\LaravelPermissionGenerator\PermissionGeneratorServiceProvider" --tag="permission-generator-config"
    ```
    This creates `config/permission-generator.php` in your application.

2.  **Customize the Configuration:**
    Open `config/permission-generator.php` and define:
    *   `output_enum`: The path where your `PermissionEnum.php` file will be created (e.g., `app_path('Enums/PermissionEnum.php')`).
    *   `enum_class`: The fully qualified class name of your enum (e.g., `App\Enums\PermissionEnum`).
    *   `resources`: List your application resources (e.g., 'user', 'post') and their associated actions. Define actions using:
        *   **Strings (Recommended):** Use simple strings like `'view_scoped'`, `'create'`, `'update_scoped'`, or custom action names like `'publish'`, `'manage_roles'`. The generator recognizes specific string values like `'view_scoped'` to automatically create `_any` and simple/`_own` versions. Other strings generate literal `resource.action` permissions.
        *   **`PermissionAction` Enum (Optional):** For standard patterns recognized by the generator, you can optionally `use SaeidSharafi\LaravelPermissionGenerator\Enums\PermissionAction;` and use constants like `PermissionAction::VIEW_SCOPED` for clarity.
        *   **Your Own Backed Enums (Advanced):** You can use constants from your application's own `BackedEnum` classes (e.g., `App\Enums\MyActions::APPROVE`). The generator will use the Enum case's value. **Important:** Special logic (like `_scoped` generating two permissions) is only triggered by specific *string values* recognized by the package (like `'view_scoped'`), not by the structure of your custom Enum.
    *   `custom_permissions`: (Optional) Define standalone permissions not tied to a resource.
    *   `super_admin_role`: (Optional) Name of the role to grant all permissions.
    *   `remove_stale_permissions`: (Optional) Set to `true` to enable cleanup of old permissions during sync (use with caution).

3.  **Customize Enum Templates (Optional):**
    ```bash
    php artisan vendor:publish --provider="SaeidSharafi\LaravelPermissionGenerator\PermissionGeneratorServiceProvider" --tag="permission-generator-stubs"
    ```
    This publishes the enum template to `stubs/vendor/permission-generator/enum.stub` where you can customize it.

## Usage Workflow

1.  **Configure:** Define your desired permission structure in `config/permission-generator.php`.

2.  **Generate Enum:** Create or update your `PermissionEnum.php` file:
    ```bash
    php artisan permissions:generate-enum
    ```
    *   Use `--force` to overwrite without confirmation.

3.  **Sync Database:** Ensure the permissions defined in your Enum exist in the database for `spatie/laravel-permission`:
    ```bash
    php artisan permissions:sync
    ```
    *   Use `--fresh` **with extreme caution** to delete *all* existing permissions and their assignments before syncing.
    *   Use `--yes` or `-Y` to skip confirmation prompts.

4.  **Use the Enum:** Import and use your generated Enum (e.g., `App\Enums\PermissionEnum`) in your code (Policies, Middleware, Controllers, Filament, etc.) for type safety and auto-completion.
    ```php
    use App\Enums\PermissionEnum; // Adjust namespace if you changed the output path
    
    // Example Policy
    public function updateAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionEnum::POST_UPDATE_ANY->value);
    }
    
    // Example Middleware or Controller Check
    if (! Auth::user()?->can(PermissionEnum::ACCESS_ADMIN_DASHBOARD->value)) {
        abort(403);
    }
    ```

## Streamlined Workflow

The package now supports a more streamlined workflow:

1. If you run `permissions:sync` without first generating the enum file, it will automatically run `permissions:generate-enum` for you.
2. This makes it easier to get started with minimal setup - just configure your permissions and run `php artisan permissions:sync`.

## Configuration Details

See the comments within the published `config/permission-generator.php` file for detailed explanations of each option and how to define resources and actions using strings or Enums.

## Development (Linking Local Package)

If you want to contribute or modify the package locally:

1.  Clone the package repository separately.
2.  In your main Laravel project's `composer.json`, add a `repositories` section:
    ```json
    "repositories": [
        {
            "type": "path",
            "url": "../path/to/your/local/laravel-permission-generator"
        }
    ],
    ```
3.  Require the package with `@dev` stability in your project's `composer.json`:
    ```json
    "require": {
        "saeidsharafi/laravel-permission-generator": "@dev",
        // ... other requires ...
    }
    ```
4.  Run `composer update saeidsharafi/laravel-permission-generator`.

## License

The MIT License (MIT). Please see the [LICENSE](LICENSE) file for more information.
