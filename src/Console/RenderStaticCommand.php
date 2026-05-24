<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Support\PageRenderer;

class RenderStaticCommand extends Command
{
    protected $signature = 'page-studio:render-static
        {route : Route name (e.g. users.show) OR numeric id}
        {--out= : Output file path · defaults to storage/app/page-studio/<route>.html}
        {--vars= : JSON map of variable values to substitute into the URL placeholders}';

    protected $description = 'Render a saved page to a single static HTML file using the variable examples (or --vars override)';

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

        $page = Page::where('route_id', $route->id)->first();
        if (! $page) {
            $this->error("Route `{$route->name}` has no page authored.");
            return self::FAILURE;
        }

        // Build variable context · CLI override wins over the variable's
        // first example value.
        $overrides = $this->option('vars') ? (array) json_decode((string) $this->option('vars'), true) : [];
        $context = $page->previewContext();
        foreach ($overrides as $k => $v) $context[$k] = $v;

        // Run any node graph on top of the base context.
        if ($graph = NodeGraph::where('route_id', $route->id)->first()) {
            $context = NodeGraphEngine::evaluate(
                (array) $graph->nodes,
                (array) $graph->edges,
                $context,
            );
        }

        $body = PageRenderer::render((array) $page->blocks, $context);
        $title = htmlspecialchars($route->name ?: 'Page', ENT_QUOTES);
        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
            ."<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            ."<title>{$title}</title>"
            ."<style>body{font-family:-apple-system,system-ui,sans-serif;margin:0;color:#1a1a1a;background:#fff}main{max-width:60rem;margin:0 auto;padding:2rem 1.5rem}</style>"
            ."</head><body><main>{$body}</main></body></html>";

        $out = (string) ($this->option('out') ?: storage_path("app/page-studio/{$route->name}.html"));
        if (! is_dir(dirname($out))) mkdir(dirname($out), 0775, true);
        file_put_contents($out, $html);

        $this->info("Rendered → $out");
        return self::SUCCESS;
    }
}
