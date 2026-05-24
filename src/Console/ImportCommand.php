<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;

class ImportCommand extends Command
{
    protected $signature = 'page-studio:import
        {file : Path to a JSON bundle produced by page-studio:export}
        {--rename= : Save the imported route under a new name · use when the source name collides}';

    protected $description = 'Import a page-studio bundle · variables / route / page / graph are upserted by name';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: $file");
            return self::FAILURE;
        }
        $bundle = json_decode((string) file_get_contents($file), true);
        if (! is_array($bundle) || ! isset($bundle['route'])) {
            $this->error('Not a valid page-studio bundle.');
            return self::FAILURE;
        }

        // Variables first so route segments can link to them.
        foreach ($bundle['variables'] ?? [] as $v) {
            Variable::updateOrCreate(['name' => $v['name']], $v);
        }

        $routeData = $bundle['route'];
        $routeName = (string) ($this->option('rename') ?: $routeData['name']);
        $route = RouteDefinition::updateOrCreate(
            ['name' => $routeName],
            [
                'method'        => $routeData['method'] ?? 'GET',
                'path_template' => $routeData['path_template'] ?? '/',
                'description'   => $routeData['description'] ?? null,
            ],
        );

        // Rebuild segments.
        $route->segments()->delete();
        foreach ($routeData['segments'] ?? [] as $s) {
            $varId = null;
            if ($s['kind'] === 'variable' && ! empty($s['variable_name'])) {
                $varId = Variable::where('name', $s['variable_name'])->value('id');
            }
            $route->segments()->create([
                'position'      => $s['position'],
                'kind'          => $s['kind'],
                'literal_value' => $s['literal_value'] ?? null,
                'variable_id'   => $varId,
            ]);
        }

        // Page + graph.
        if (! empty($bundle['page']['blocks'])) {
            Page::updateOrCreate(['route_id' => $route->id], ['blocks' => $bundle['page']['blocks']]);
        }
        if (! empty($bundle['graph'])) {
            NodeGraph::updateOrCreate(
                ['route_id' => $route->id],
                ['nodes' => $bundle['graph']['nodes'] ?? [], 'edges' => $bundle['graph']['edges'] ?? []],
            );
        }

        $this->info("Imported route `{$route->name}` (id {$route->id}).");
        return self::SUCCESS;
    }
}
