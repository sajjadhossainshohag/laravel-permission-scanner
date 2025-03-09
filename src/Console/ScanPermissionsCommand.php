<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner\Console;

use Illuminate\Console\Command;
use Sajjadhossainshohag\LaravelPermissionScanner\Parser\PermissionExtractor;

class ScanPermissionsCommand extends Command
{
    protected $signature = 'permission:scan {--path=resources/views : The path to scan}';

    protected $description = 'Scan for permissions in the given path (default: app)';

    public function handle()
    {
        $path = base_path($this->option('path'));
        $this->info("Scanning for permissions in: {$path}");

        $results = PermissionExtractor::scan($path);

        dd(collect($results)->flatten()->unique()->sort()->values()->all());
    }
}
