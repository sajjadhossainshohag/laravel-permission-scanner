# Laravel Permission Scanner

The **Laravel Permission Scanner** helps you manage role-based access control (RBAC) in your Laravel application by scanning for the usage of `@can`, `@canany`, and permission middleware in controllers, routes, and Blade views. It provides an easy way to analyze and track permission-related directives across your app.

> Note: Laravel Permission Scanner is currently in beta version.

## Features

- Scans PHP files for `@can`, `@canany`, `middleware`, `Gate`, and permission methods
- Detects permission usage in:
  - Controllers
  - Routes
  - Blade views
  - Middleware
- Analyzes permissions using AST parsing
- Generates permission seeders automatically
- Provides detailed results of found permissions across files
- Supports debugging with detailed file and permission output

## Requirements

- PHP 8.1 or higher
- Laravel 8.0 or higher

## Installation

Install the package via Composer:

```bash
composer require sajjadhossainshohag/laravel-permission-scanner:v0.1.0-beta
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Sajjadhossainshohag\LaravelPermissionScanner\PermissionScannerServiceProvider"
```

This will create a `config/scanner.php` file where you can customize the scan paths:

```php
return [
    'scan_paths' => [
        'resources/views',
        'app',
        'routes',
    ],
];
```

## Usage

### Basic Scanning

Run the command to scan your application:

```bash
php artisan permission:scan
```

This will display all found permissions in your application.

### Generate Permission Seeder

To automatically generate a seeder file with found permissions:

```bash
php artisan permission:scan --seeder=PermissionsTableSeeder
```

This will create a new seeder file in `database/seeders` with all discovered permissions.

Example output:
```php
class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['name' => 'edit-posts', 'guard_name' => 'web'],
            ['name' => 'delete-posts', 'guard_name' => 'web'],
            // ...
        ];

        DB::table('permissions')->insert($permissions);
    }
}
```

## What It Scans For

The scanner detects permissions in:

1. Blade Directives:
   - `@can('permission-name')`
   - `@canany(['permission-1', 'permission-2'])`

2. Controller Methods:
   - `$this->authorize('permission-name')`
   - `Gate::allows('permission-name')`

3. Route Definitions:
   - `->middleware('can:permission-name')`
   - `->middleware('permission:permission-name')`

4. Policy Methods:
   - Permission-related method names and checks

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Development

```bash
# Install dependencies
composer install

# Run tests
composer test
```

## License

This package is open-source software licensed under the [MIT license](LICENSE).

## Credits

This package is open-source software licensed under the [MIT license](LICENSE).
