<?php

namespace LoggedCloud\PageStudio\Templates;

/**
 * A starter template seeds a route + its variables + page blocks + node
 * graph in one shot. Drop a subclass into `app/PageStudio/Templates/`
 * (or register explicitly via `TemplateRegistry::register`) and the
 * `page-studio:install-template <name>` artisan command will install it.
 */
abstract class Template
{
    /**
     * Convenience for `blocks()` · returns a freshly-made block with the
     * given type and merged settings (overwriting the type's defaults).
     */
    public static function block(string $type, array $settings = []): array
    {
        $b = \LoggedCloud\PageStudio\Support\BlockFactory::make($type) ?? [
            'id' => 'b_'.bin2hex(random_bytes(4)), 'type' => $type, 'settings' => [],
        ];
        $b['settings'] = array_merge($b['settings'] ?? [], $settings);
        return $b;
    }

    /** Slug used on the command line · convention: kebab-case. */
    abstract public static function name(): string;

    /** Human label shown in pickers. */
    abstract public static function label(): string;

    /** One-sentence description. */
    public static function description(): string
    {
        return '';
    }

    /**
     * Route definition shape:
     *   ['name' => 'blog.show', 'method' => 'GET', 'path_template' => '/blog/{slug}',
     *    'description' => '...',
     *    'segments' => [
     *        ['position' => 0, 'kind' => 'literal', 'literal_value' => 'blog'],
     *        ['position' => 1, 'kind' => 'variable', 'variable_name' => 'slug'],
     *    ]]
     *
     * @return array<string, mixed>
     */
    abstract public static function route(): array;

    /**
     * Variables the template depends on. Each item is the create payload
     * for `Variable::updateOrCreate(['name' => $name], $payload)`:
     *   ['name' => 'slug', 'type' => 'slug', 'examples' => [...]]
     *
     * @return array<int, array<string, mixed>>
     */
    public static function variables(): array
    {
        return [];
    }

    /**
     * Page block tree saved into `page_studio_pages.blocks`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function blocks(): array
    {
        return [];
    }

    /**
     * Node graph saved into `page_studio_node_graphs`. Empty arrays = no graph.
     *
     * @return array{nodes: array, edges: array}
     */
    public static function graph(): array
    {
        return ['nodes' => [], 'edges' => []];
    }
}
