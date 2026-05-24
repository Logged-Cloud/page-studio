<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Templates\TemplateRegistry;

class InstallTemplateCommand extends Command
{
    protected $signature = 'page-studio:install-template
        {name? : Template slug (omit to list available templates)}
        {--rename= : Override the route name from the template (useful when the default collides)}';

    protected $description = 'Install a starter template · creates the route + variables + page + node graph from a Template class';

    public function handle(): int
    {
        $name = $this->argument('name');
        if (! $name) {
            $this->listTemplates();
            return self::SUCCESS;
        }

        $class = TemplateRegistry::find((string) $name);
        if (! $class) {
            $this->error("No template registered for `$name`.");
            $this->listTemplates();
            return self::FAILURE;
        }

        $routeData = $class::route();
        $routeName = (string) ($this->option('rename') ?: ($routeData['name'] ?? $name));

        foreach ($class::variables() as $v) {
            Variable::updateOrCreate(['name' => $v['name']], $v);
        }

        $route = RouteDefinition::updateOrCreate(
            ['name' => $routeName],
            [
                'method'        => $routeData['method'] ?? 'GET',
                'path_template' => $routeData['path_template'] ?? '/',
                'description'   => $routeData['description'] ?? null,
            ],
        );

        $route->segments()->delete();
        foreach ($routeData['segments'] ?? [] as $s) {
            $varId = null;
            if (($s['kind'] ?? null) === 'variable' && ! empty($s['variable_name'])) {
                $varId = Variable::where('name', $s['variable_name'])->value('id');
            }
            $route->segments()->create([
                'position'      => $s['position'],
                'kind'          => $s['kind'],
                'literal_value' => $s['literal_value'] ?? null,
                'variable_id'   => $varId,
            ]);
        }

        if (! empty($class::blocks())) {
            Page::updateOrCreate(['route_id' => $route->id], ['blocks' => $class::blocks()]);
        }

        $graph = $class::graph();
        if (! empty($graph['nodes']) || ! empty($graph['edges'])) {
            NodeGraph::updateOrCreate(
                ['route_id' => $route->id],
                ['nodes' => $graph['nodes'] ?? [], 'edges' => $graph['edges'] ?? []],
            );
        }

        $this->info("Installed `{$class::label()}` as route `{$route->name}` (id {$route->id}).");
        return self::SUCCESS;
    }

    protected function listTemplates(): void
    {
        $rows = [];
        foreach (TemplateRegistry::all() as $name => $class) {
            $rows[] = [$name, $class::label(), $class::description()];
        }
        if (empty($rows)) {
            $this->line('No templates registered.');
            return;
        }
        $this->table(['Slug', 'Label', 'Description'], $rows);
    }
}
