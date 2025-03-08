# Laravel Permission Scanner

The **Laravel Permission Scanner** helps you manage role-based access control (RBAC) in your Laravel application by scanning for the usage of `@can`, `@canany`, and permission middleware in controllers, routes, and Blade views. It provides an easy way to analyze and track permission-related directives across your app.

## Features

- Scans PHP files for `@can`, `@canany`, `middleware`, `Gate`, and permission methods.
- Detects permission usage in controllers, routes, and Blade views.
- Analyzes permissions using AST parsing.
- Supports scanning middleware and method calls for permissions.
- Provides detailed results of found permissions across files.
- Allows debugging with detailed file and permission output.

## Installation
Install the package via Composer:

   ```bash
   composer require sajjadhossainshohag/laravel-permission-scanner
   ```
## Usage
1. Run the command to scan your application:

   ```bash
   php artisan permission:scan
   ```

2. The command will scan your application for permissions and display the results.

## Contributing
If you have any suggestions or improvements, feel free to open an issue or submit a pull request. Your contributions are greatly appreciated!

## License
This package is open-source software licensed under the [MIT license](LICENSE).