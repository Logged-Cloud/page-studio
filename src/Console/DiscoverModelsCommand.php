<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Support\ModelDiscovery;

class DiscoverModelsCommand extends Command
{
    protected $signature = 'page-studio:discover-models
        {--dir= : Override the directory to scan (default: app/Models)}
        {--namespace=App\\Models : PSR-4 namespace prefix for the discovered files}';

    protected $description = 'Scan app/Models and cache the FQCN list for the Model finder node dropdown.';

    public function handle(): int
    {
        $dir = $this->option('dir') ?: app_path('Models');
        $ns  = $this->option('namespace');

        $map = ModelDiscovery::scan($dir, $ns);
        ModelDiscovery::writeCache($map);

        $this->info(sprintf('Cached %d model(s) → %s', count($map), ModelDiscovery::cachePath()));
        if (count($map) === 0) {
            $this->warn('No Eloquent models found · the Model finder dropdown will fall back to a text input.');
        }
        return self::SUCCESS;
    }
}
