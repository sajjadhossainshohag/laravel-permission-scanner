<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner;

use Illuminate\Support\ServiceProvider;
use Sajjadhossainshohag\LaravelPermissionScanner\Console\ScanPermissionsCommand;

class PermissionScannerServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register the ScanPermissionsCommand
        $this->commands([
            ScanPermissionsCommand::class,
        ]);

        // Merge the config file
        $this->mergeConfigFrom(__DIR__.'/../config/scanner.php', 'scanner');

        // Publish the config file
        $this->publishes([
            __DIR__.'/../config/scanner.php' => config_path('scanner.php'),
        ]);
    }

    public function boot()
    {
        //
    }
}
