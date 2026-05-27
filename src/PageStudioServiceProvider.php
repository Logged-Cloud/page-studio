<?php

namespace LoggedCloud\PageStudio;

use Illuminate\Support\ServiceProvider;

class PageStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/page-studio.php', 'page-studio');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\RenderStaticCommand::class,
                Console\ExportStaticSiteCommand::class,
                Console\ExportCommand::class,
                Console\ImportCommand::class,
                Console\DiscoverModelsCommand::class,
                Console\InstallTemplateCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'page-studio');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/page-studio.php' => config_path('page-studio.php'),
        ], 'page-studio-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'page-studio-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/page-studio'),
        ], 'page-studio-views');

        $this->registerPageRoutes();
        $this->registerBuiltinNodes();
        $this->registerBuiltinBlocks();
        $this->registerBuiltinTemplates();
        $this->discoverNodeTypes();
        $this->discoverBlockTypes();
        $this->discoverTemplates();
        // Model finder dropdown promotion MUST run after the discovery
        // pass · discoverNodeTypes rebuilds every node's library entry
        // from toLibraryEntry() and would otherwise wipe the
        // kind=select swap.
        $this->injectModelOptions();

        // Gate on the Livewire container binding · `class_exists` alone returns
        // true for autoloaded classes whose service provider has not booted,
        // which then blows up the first call to ::component().
        if (class_exists(\Livewire\Livewire::class) && $this->app->bound('livewire')) {
            \Livewire\Livewire::component('page-studio.route-builder', \LoggedCloud\PageStudio\Livewire\RouteBuilder::class);
            \Livewire\Livewire::component('page-studio.variable-library', \LoggedCloud\PageStudio\Livewire\VariableLibrary::class);
            \Livewire\Livewire::component('page-studio.page-builder', \LoggedCloud\PageStudio\Livewire\PageBuilder::class);
        }
    }

    /**
     * Bind every saved RouteDefinition as a real Laravel route so the URL
     * the author built in the studio actually serves the page they made.
     * Pre-existing host-app routes win · we use Route::hasName() with our
     * `page-studio.<name>` prefix and skip on collision.
     */
    protected function registerPageRoutes(): void
    {
        try {
            $prefix = config('page-studio.table_prefix', 'page_studio_');
            if (! \Illuminate\Support\Facades\Schema::hasTable($prefix.'routes')) return;

            $routes = Models\RouteDefinition::with('segments.variable')->get();
            foreach ($routes as $rd) {
                $compiled = Support\RouteCompiler::compile($rd);
                $name     = 'page-studio.'.$rd->name;

                // Skip if the host app already registered a route with this name
                // or this path · prevents accidental overrides of host-defined
                // application routes.
                if (\Illuminate\Support\Facades\Route::has($name)) continue;

                $verb = strtolower($rd->method ?: 'get');
                $route = \Illuminate\Support\Facades\Route::match([$verb], $compiled['template'], function (...$args) use ($rd) {
                    return $this->renderStudioRoute($rd, $args);
                })->name($name);

                if (! empty($compiled['where'])) {
                    $route->where($compiled['where']);
                }
            }
        } catch (\Throwable) {
            // The host app may still be mid-migration · don't crash boot.
        }
    }

    /**
     * Auto-register every shipped `NodeType` subclass under
     * `src/Nodes/Builtin/` so the engine + palette dispatch through the
     * registry first. Each class converted from the legacy config-based
     * definition gets added here; types not yet converted continue to be
     * served by the engine's fallback match() + the config schema.
     */
    protected function registerBuiltinNodes(): void
    {
        $builtins = [
            // Sources
            Nodes\Builtin\SourceRouteVariableNode::class,
            Nodes\Builtin\SourceConstantNode::class,
            Nodes\Builtin\SourceColorNode::class,
            Nodes\Builtin\SourceAuthUserNode::class,
            Nodes\Builtin\SourceAuthIdNode::class,
            Nodes\Builtin\SourceRequestNode::class,
            Nodes\Builtin\SourceNowNode::class,
            Nodes\Builtin\SourceModelFinderNode::class,
            Nodes\Builtin\SourceHttpFetchNode::class,
            // Text + value transforms
            Nodes\Builtin\TransformUppercaseNode::class,
            Nodes\Builtin\TransformLowercaseNode::class,
            Nodes\Builtin\TransformTrimNode::class,
            Nodes\Builtin\TransformConcatNode::class,
            Nodes\Builtin\TransformReplaceNode::class,
            Nodes\Builtin\TransformSlugifyNode::class,
            Nodes\Builtin\TransformLengthNode::class,
            Nodes\Builtin\TransformSplitNode::class,
            Nodes\Builtin\TransformJoinNode::class,
            Nodes\Builtin\TransformFormatDateNode::class,
            Nodes\Builtin\TransformDefaultNode::class,
            Nodes\Builtin\TransformFieldNode::class,
            Nodes\Builtin\TransformEqualsNode::class,
            Nodes\Builtin\TransformIfNode::class,
            Nodes\Builtin\TransformFirstNode::class,
            Nodes\Builtin\TransformMathNode::class,
            Nodes\Builtin\TransformLoopMapNode::class,
            Nodes\Builtin\TransformSwitchCaseNode::class,
            Nodes\Builtin\TransformCurrencyFormatNode::class,
            Nodes\Builtin\TransformNumberFormatNode::class,
            // Convert
            Nodes\Builtin\ConvertToStringNode::class,
            Nodes\Builtin\ConvertToIntNode::class,
            Nodes\Builtin\ConvertToBoolNode::class,
            Nodes\Builtin\ConvertToArrayNode::class,
            // Image pipeline
            Nodes\Builtin\ImageSourceNode::class,
            Nodes\Builtin\ImageUploadNode::class,
            Nodes\Builtin\ImageSolidNode::class,
            Nodes\Builtin\ImageGradientNode::class,
            Nodes\Builtin\ImageStripesNode::class,
            Nodes\Builtin\ImageCheckerboardNode::class,
            Nodes\Builtin\ImageNoiseNode::class,
            Nodes\Builtin\ImageBrightnessNode::class,
            Nodes\Builtin\ImageContrastNode::class,
            Nodes\Builtin\ImageSaturateNode::class,
            Nodes\Builtin\ImageGrayscaleNode::class,
            Nodes\Builtin\ImageSepiaNode::class,
            Nodes\Builtin\ImageInvertNode::class,
            Nodes\Builtin\ImageHueRotateNode::class,
            Nodes\Builtin\ImageBlurNode::class,
            Nodes\Builtin\ImageOpacityNode::class,
            // Output + note
            Nodes\Builtin\OutputNode::class,
            Nodes\Builtin\NoteNode::class,
        ];
        foreach ($builtins as $class) {
            try {
                Nodes\NodeRegistry::register($class);
            } catch (\Throwable) {
                // Ignore registration failures so a single bad class doesn't crash boot.
            }
        }
    }

    /**
     * Auto-register the shipped `BlockType` built-ins so the renderer
     * dispatches through the registry. Mirrors `registerBuiltinNodes`.
     */
    protected function registerBuiltinBlocks(): void
    {
        $builtins = [
            // Content
            Blocks\Builtin\HeadingBlock::class,
            Blocks\Builtin\ParagraphBlock::class,
            Blocks\Builtin\ButtonBlock::class,
            Blocks\Builtin\ImageBlock::class,
            Blocks\Builtin\ListBlock::class,
            Blocks\Builtin\TableBlock::class,
            Blocks\Builtin\QuoteBlock::class,
            Blocks\Builtin\CodeBlock::class,
            Blocks\Builtin\DividerBlock::class,
            Blocks\Builtin\SpacerBlock::class,
            Blocks\Builtin\SignatureBlock::class,
            Blocks\Builtin\SocialBlock::class,
            Blocks\Builtin\EmbedBlock::class,
            Blocks\Builtin\VideoBlock::class,
            Blocks\Builtin\AccordionBlock::class,
            Blocks\Builtin\AnimatedTextBlock::class,
            Blocks\Builtin\ImageCarouselBlock::class,
            // Layout
            Blocks\Builtin\HeroBlock::class,
            Blocks\Builtin\SectionBlock::class,
            Blocks\Builtin\ColumnsBlock::class,
            Blocks\Builtin\ColumnsThreeBlock::class,
            Blocks\Builtin\CardBlock::class,
            Blocks\Builtin\ConditionalBlock::class,
            Blocks\Builtin\PanelBlock::class,
            Blocks\Builtin\TabsBlock::class,
        ];
        foreach ($builtins as $class) {
            try {
                Blocks\BlockRegistry::register($class);
                // Mirror into config('page-studio.blocks') so the palette
                // (which still iterates the config) lists them.
                $library = config('page-studio.blocks', []);
                $library[$class::key()] = $class::toLibraryEntry();
                config(['page-studio.blocks' => $library]);
            } catch (\Throwable) {}
        }
    }

    protected function registerBuiltinTemplates(): void
    {
        $builtins = [
            Templates\Builtin\BlogPostTemplate::class,
            Templates\Builtin\UserProfileTemplate::class,
            Templates\Builtin\LandingTemplate::class,
            Templates\Builtin\ProductDetailTemplate::class,
            Templates\Builtin\PricingTemplate::class,
            Templates\Builtin\DocsTemplate::class,
            Templates\Builtin\ContactTemplate::class,
            Templates\Builtin\ComingSoonTemplate::class,
        ];
        foreach ($builtins as $class) {
            try {
                Templates\TemplateRegistry::register($class);
            } catch (\Throwable) {}
        }
    }

    /**
     * Auto-discover host-app Template classes under `app/PageStudio/Templates/`
     * so projects can ship their own starters alongside the built-ins.
     */
    protected function discoverTemplates(): void
    {
        try {
            $paths = (array) config('page-studio.template_paths', [
                ['dir' => function_exists('app_path') ? app_path('PageStudio/Templates') : '', 'namespace' => 'App\\PageStudio\\Templates'],
            ]);

            foreach ($paths as $entry) {
                $dir = $entry['dir'] ?? '';
                $ns  = $entry['namespace'] ?? '';
                if (! $dir || ! $ns || ! is_dir($dir)) continue;

                $rii = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($rii as $file) {
                    if (! $file->isFile() || $file->getExtension() !== 'php') continue;
                    $rel   = trim(str_replace([$dir, '.php'], '', $file->getPathname()), DIRECTORY_SEPARATOR);
                    if ($rel === '') continue;
                    $class = $ns.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
                    if (! class_exists($class)) continue;
                    try {
                        $ref = new \ReflectionClass($class);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($ref->isAbstract() || ! $ref->isSubclassOf(Templates\Template::class)) continue;
                    Templates\TemplateRegistry::register($class);
                }
            }
        } catch (\Throwable) {
            // Best-effort · never crash boot.
        }
    }

    /**
     * Discover host-app block classes from `app/PageStudio/Blocks/`.
     * Mirrors the node-type discovery path.
     */
    protected function discoverBlockTypes(): void
    {
        try {
            $paths = (array) config('page-studio.block_paths', [
                ['dir' => function_exists('app_path') ? app_path('PageStudio/Blocks') : '', 'namespace' => 'App\\PageStudio\\Blocks'],
            ]);

            foreach ($paths as $entry) {
                $dir = $entry['dir'] ?? '';
                $ns  = $entry['namespace'] ?? '';
                if (! $dir || ! $ns || ! is_dir($dir)) continue;

                $rii = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($rii as $file) {
                    if (! $file->isFile() || $file->getExtension() !== 'php') continue;
                    $rel   = trim(str_replace([$dir, '.php'], '', $file->getPathname()), DIRECTORY_SEPARATOR);
                    if ($rel === '') continue;
                    $class = $ns.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
                    if (! class_exists($class)) continue;
                    try {
                        $ref = new \ReflectionClass($class);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($ref->isAbstract() || ! $ref->isSubclassOf(Blocks\BlockType::class)) continue;
                    Blocks\BlockRegistry::register($class);
                }
            }

            if (! empty(Blocks\BlockRegistry::all())) {
                $library = config('page-studio.blocks', []);
                foreach (Blocks\BlockRegistry::all() as $key => $class) {
                    $library[$key] = $class::toLibraryEntry();
                }
                config(['page-studio.blocks' => $library]);
            }
        } catch (\Throwable) {
            // Best-effort discovery · never crash host-app boot.
        }
    }

    /**
     * Walk `app/PageStudio/Nodes` (or any path the host configures) for
     * developer-defined `NodeType` subclasses, register them, and merge
     * the resulting library entries into `page-studio.nodes` so the rest
     * of the studio treats them as first-class palette types.
     */
    protected function discoverNodeTypes(): void
    {
        try {
            $paths = (array) config('page-studio.node_paths', [
                ['dir' => function_exists('app_path') ? app_path('PageStudio/Nodes') : '', 'namespace' => 'App\\PageStudio\\Nodes'],
            ]);

            foreach ($paths as $entry) {
                $dir = $entry['dir'] ?? '';
                $ns  = $entry['namespace'] ?? '';
                if (! $dir || ! $ns || ! is_dir($dir)) continue;

                $rii = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                );
                foreach ($rii as $file) {
                    if (! $file->isFile() || $file->getExtension() !== 'php') continue;
                    $rel   = trim(str_replace([$dir, '.php'], '', $file->getPathname()), DIRECTORY_SEPARATOR);
                    if ($rel === '') continue;
                    $class = $ns.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
                    if (! class_exists($class)) continue;
                    try {
                        $ref = new \ReflectionClass($class);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($ref->isAbstract() || ! $ref->isSubclassOf(Nodes\NodeType::class)) continue;
                    Nodes\NodeRegistry::register($class);
                }
            }

            // Push the registry into the live config so palette + engine see them.
            if (! empty(Nodes\NodeRegistry::all())) {
                $library = config('page-studio.nodes', []);
                foreach (Nodes\NodeRegistry::all() as $key => $class) {
                    $library[$key] = $class::toLibraryEntry();
                }
                config(['page-studio.nodes' => $library]);
            }
        } catch (\Throwable) {
            // Discovery is best-effort · never crash host-app boot.
        }
    }

    /**
     * Promote the Model finder's `model_class` setting from a free-text
     * field to a populated select. Sourced from the discovered model map
     * (composer-cached via `page-studio:discover-models` · falls back to
     * an on-the-fly scan).
     */
    protected function injectModelOptions(): void
    {
        try {
            $map = Support\ModelDiscovery::map();
            if (empty($map)) return;

            $library = config('page-studio.nodes', []);
            if (! isset($library['source.model_finder'])) return;

            $library['source.model_finder']['settings']['model_class']['kind']    = 'select';
            $library['source.model_finder']['settings']['model_class']['options'] = $map;

            $current = $library['source.model_finder']['settings']['model_class']['default'] ?? null;
            if (! isset($map[$current])) {
                $library['source.model_finder']['settings']['model_class']['default'] = array_key_first($map);
            }

            config(['page-studio.nodes' => $library]);
        } catch (\Throwable) {
            // Discovery is best-effort · never crash the host app boot.
        }
    }

    /**
     * Build the rendering context from URL parameters + the node graph and
     * return the page HTML.
     */
    protected function renderStudioRoute(Models\RouteDefinition $rd, array $args): \Illuminate\Http\Response
    {
        $page = Models\Page::where('route_id', $rd->id)->first();
        if (! $page) abort(404, 'No page authored for this route.');

        // Drafts + scheduled-future pages stay hidden behind the auto-routes
        // so the public URL never leaks them. Hosts that want to preview a
        // draft can still bind the PageBuilder Livewire component to the
        // pageId directly behind their own auth gate.
        if (($page->status ?? 'draft') !== 'published') {
            abort(404);
        }
        if ($page->publish_at !== null && $page->publish_at->isFuture()) {
            abort(404);
        }

        // Match positional route args back to the variable names declared on
        // the segments · Laravel hands them to us in the order they appear.
        $context = [];
        $i = 0;
        foreach ($rd->segments as $segment) {
            if ($segment->kind === 'variable' && $segment->variable) {
                $context[$segment->variable->name] = $args[$i] ?? null;
                $i++;
            }
        }

        // Layer in any node-graph outputs.
        $graph = Models\NodeGraph::where('route_id', $rd->id)->first();
        if ($graph) {
            $context = Support\NodeGraphEngine::evaluate(
                (array) $graph->nodes,
                (array) $graph->edges,
                $context,
            );
        }

        // Sanitise the block tree against the current block-type config so a
        // host-app upgrade that renames or removes a block type doesn't take
        // the rendered page down · unknown types are skipped, missing
        // settings filled with defaults.
        $blocks = Support\BlockTree::sanitise((array) $page->blocks);
        $body = Support\PageRenderer::render($blocks, $context);

        $html = "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
            ."<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            ."<title>".htmlspecialchars($rd->name ?: 'Page', ENT_QUOTES)."</title>"
            ."<style>body{font-family:-apple-system,system-ui,sans-serif;margin:0;color:#1a1a1a}main{max-width:60rem;margin:0 auto;padding:2rem 1.5rem}</style>"
            ."</head><body><main>{$body}</main></body></html>";

        return response($html);
    }
}
