<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Sajjadhossainshohag\LaravelPermissionScanner\Parser\PermissionExtractor;

class ScanPermissionsCommand extends Command
{
    protected $signature = 'permission:scan {--seeder= : Name of the seeder file}';

    protected $description = 'Scan for permissions in the given path (default: resources/views, app, routes)';

    public function handle(): void
    {
        $paths = config('scanner.scan_paths');
        $this->info('Scanning for permissions in: ' . implode(', ', $paths));

        $results = PermissionExtractor::scan($paths);

        $permissions = collect($results)->flatten()->unique()->sort()->values()->all();
        $this->info('Found ' . count($permissions) . ' permissions: ' . implode(', ', $permissions));

        if ($this->option('seeder')) {
            $this->createSeederFile($permissions);
        }
    }

    protected function createSeederFile(array $permissions): void
    {
        $name = $this->argument('seeder') ?? 'PermissionsSeeder';
        $seederPath = __DIR__ . '/database/seeders/' . $name . '.php';;

        $content = $this->generateSeederContent($permissions, $name);

        File::put($seederPath, $content);

        $this->info('Created seeder file: ' . $seederPath);
    }

    protected function generateSeederContent(array $permissions, string $name): string
    {
        $permissionsArray = array_map(function ($permission) {
            return "            ['name' => '{$permission}', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]";
        }, $permissions);

        return <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class {$name} extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \$permissions = [
{$this->formatPermissionsArray($permissionsArray)}
        ];

        DB::table('permissions')->insert(\$permissions);
    }
}
PHP;
    }

    protected function formatPermissionsArray(array $permissions): string
    {
        return implode(",\n", $permissions);
    }
}
