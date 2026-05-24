<?php

namespace LoggedCloud\PageStudio\Console;

use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Support\BlockTree;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ExportStaticSiteCommand extends Command
{
    protected $signature = 'page-studio:export-static-site
        {--out= : Output directory · defaults to storage/app/page-studio/static}';

    protected $description = 'Render every saved route as an HTML file under the picked output directory';

    public function handle(): int
    {
        $out = (string) ($this->option('out') ?: storage_path('app/page-studio/static'));
        if (! is_dir($out)) mkdir($out, 0775, true);

        $routes = RouteDefinition::with('segments.variable')->get();
        if ($routes->isEmpty()) {
            $this->info('No routes to export.');
            return self::SUCCESS;
        }

        $written = 0;
        foreach ($routes as $route) {
            $page = Page::where('route_id', $route->id)->first();
            if (! $page) continue;

            // Build the preview context · same shape as PageBuilder::routeContext,
            // using each variable's first example value.
            $context = [];
            foreach ($route->segments as $segment) {
                if ($segment->kind !== 'variable' || ! $segment->variable) continue;
                $examples = (array) $segment->variable->examples;
                $context[$segment->variable->name] = $examples[0] ?? '';
            }

            // Layer any node-graph outputs on top of the base context.
            if ($graph = NodeGraph::where('route_id', $route->id)->first()) {
                $context = NodeGraphEngine::evaluate(
                    (array) $graph->nodes,
                    (array) $graph->edges,
                    $context,
                );
            }

            $blocks = BlockTree::sanitise((array) $page->blocks);
            $body   = PageRenderer::render($blocks, $context);
            $title  = htmlspecialchars($route->name ?: 'Page', ENT_QUOTES);
            $html   = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
                ."<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
                ."<title>{$title}</title>"
                ."<style>body{font-family:-apple-system,system-ui,sans-serif;margin:0;color:#1a1a1a;background:#fff}main{max-width:60rem;margin:0 auto;padding:2rem 1.5rem}</style>"
                ."</head><body><main>{$body}</main></body></html>";

            // Replace dots in route names so the filename is safe on every OS.
            $filename = str_replace('.', '-', (string) $route->name).'.html';
            $path     = rtrim($out, '/').'/'.$filename;
            file_put_contents($path, $html);
            $this->info("Wrote {$path}");
            $written++;
        }

        $this->info("Done · {$written} file(s) written to {$out}.");
        return self::SUCCESS;
    }
}
