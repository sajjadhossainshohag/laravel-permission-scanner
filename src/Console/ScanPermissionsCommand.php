<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner\Console;

use Illuminate\Console\Command;
use Sajjadhossainshohag\LaravelPermissionScanner\Parser\PermissionExtractor;

class ScanPermissionsCommand extends Command
{
    protected $signature = 'permission:scan';

    protected $description = 'Scan for permissions in the given path (default: resources/views, app, routes)';

    public function handle()
    {
        $paths = config('scanner.scan_paths');
        $this->info('Scanning for permissions in: '.implode(', ', $paths));

        $results = PermissionExtractor::scan($paths);

        dd(collect($results)->flatten()->unique()->sort()->values()->all());
    }
}
