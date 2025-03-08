<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner;

use Illuminate\Support\ServiceProvider;
use Sajjadhossainshohag\LaravelPermissionScanner\Console\ScanPermissionsCommand;

class PermissionScannerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            ScanPermissionsCommand::class,
        ]);
    }

    public function boot()
    {
        //
    }
}
