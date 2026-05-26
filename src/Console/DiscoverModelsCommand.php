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

        // scanRecords preserves the per-model #[ExposeToModelFinder]
        // config (findBy + searchable cols) · scan() only returns the
        // bare label map and would strip those, breaking the dynamic
        // finder_key dropdown.
        $records = ModelDiscovery::scanRecords($dir, $ns);
        ModelDiscovery::writeRecordCache($records);

        $this->info(sprintf('Cached %d model(s) → %s', count($records), ModelDiscovery::cachePath()));
        if (count($records) === 0) {
            $this->warn('No #[ExposeToModelFinder]-decorated models found · the Model finder dropdown will fall back to a text input. Add the attribute to the models you want exposed.');
        }
        return self::SUCCESS;
    }
}
