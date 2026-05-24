<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;

class ExportCommand extends Command
{
    protected $signature = 'page-studio:export
        {route : Route name OR numeric id}
        {--out= : Output JSON path · defaults to storage/app/page-studio/<route>.json}';

    protected $description = 'Export a route + its variables + page + node graph as a portable JSON bundle';

    public function handle(): int
    {
        $arg = (string) $this->argument('route');
        $route = is_numeric($arg)
            ? RouteDefinition::with('segments.variable')->find((int) $arg)
            : RouteDefinition::with('segments.variable')->where('name', $arg)->first();
        if (! $route) {
            $this->error("No route found for `$arg`.");
            return self::FAILURE;
        }

        $page  = Page::where('route_id', $route->id)->first();
        $graph = NodeGraph::where('route_id', $route->id)->first();

        // Gather just the Variable rows the route actually references.
        $varNames = $route->segments
            ->where('kind', 'variable')
            ->pluck('variable.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $variables = Variable::whereIn('name', $varNames)->get();

        $bundle = [
            'page_studio_export' => '1.0',
            'exported_at'        => now()->toIso8601String(),
            'route' => [
                'name'          => $route->name,
                'method'        => $route->method,
                'path_template' => $route->path_template,
                'description'   => $route->description,
                'segments'      => $route->segments->map(fn ($s) => [
                    'position'      => $s->position,
                    'kind'          => $s->kind,
                    'literal_value' => $s->literal_value,
                    'variable_name' => $s->kind === 'variable' ? ($s->variable->name ?? null) : null,
                ])->all(),
            ],
            'variables' => $variables->map(fn ($v) => $v->only([
                'name', 'label', 'type', 'regex', 'description', 'examples',
            ]))->all(),
            'page'  => $page  ? ['blocks' => $page->blocks] : null,
            'graph' => $graph ? ['nodes'  => $graph->nodes, 'edges' => $graph->edges] : null,
        ];

        $out = (string) ($this->option('out') ?: storage_path("app/page-studio/{$route->name}.json"));
        if (! is_dir(dirname($out))) mkdir(dirname($out), 0775, true);
        file_put_contents($out, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Exported → $out");
        return self::SUCCESS;
    }
}
