<?php

namespace LoggedCloud\PageStudio\Nodes;

/**
 * Developer-defined custom node base · drop a subclass into
 * `app/PageStudio/Nodes/` (or register it explicitly via
 * `NodeRegistry::register`) and it shows up alongside the built-in
 * source / transform / image / output palette.
 *
 * Override the static descriptors to describe the node, implement
 * `evaluate()` to compute its outputs from its inputs + settings.
 */
abstract class NodeType
{
    /** Unique identifier · convention `custom.<snake_name>`. */
    abstract public static function key(): string;

    /** Label shown in palettes + node headers. */
    abstract public static function label(): string;

    /** Emoji / glyph rendered in the node header. */
    public static function icon(): string
    {
        return '◆';
    }

    /** Palette section · 'source' | 'transform' | 'image' | 'output' | 'note'. */
    public static function group(): string
    {
        return 'transform';
    }

    /** @return array<string, array{label?: string, type?: string}> */
    public static function inputs(): array
    {
        return [];
    }

    /** @return array<string, array{label?: string, type?: string}> */
    public static function outputs(): array
    {
        return ['value' => ['label' => 'Value', 'type' => 'any']];
    }

    /**
     * Per-node setting fields rendered into the right panel. Each entry
     * follows the same shape as the built-in node config:
     *   ['kind' => 'text|number|select|bool|upload|textarea|url',
     *    'label' => '...', 'default' => ..., 'options' => [...]]
     *
     * @return array<string, array<string, mixed>>
     */
    public static function settings(): array
    {
        return [];
    }

    /**
     * Run the node. `$inputs` is keyed by input socket name with the
     * resolved upstream value, `$settings` is the node's per-instance
     * settings, `$context` is the page's variable context. Return the
     * output socket values keyed by output socket name.
     *
     * @param array<string, mixed> $inputs
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    abstract public function evaluate(array $inputs, array $settings, array $context): array;

    /**
     * Optional hook for nodes whose output sockets depend on their
     * settings (e.g. a "schema reader" that exposes one socket per column
     * of a chosen model, or a "CSV reader" that exposes one socket per
     * column of a chosen file). Return `null` to keep the static
     * `outputs()` shape, or an array keyed by socket name with the same
     * shape as `outputs()` to replace it.
     *
     * The Blade canvas + the settings panel both consult this via
     * `PageBuilder::outputsFor($node)`, so the moment a setting flips
     * the canvas + the engine pick up the new socket list.
     *
     * @param array<string, mixed> $node
     * @return array<string, array{label?: string, type?: string}>|null
     */
    public function dynamicOutputs(array $node): ?array
    {
        return null;
    }

    /**
     * Optional · override one or more SETTINGS fields based on the
     * current node instance. Counterpart to dynamicOutputs() · used
     * when the schema for a field is conditional on another field's
     * value (e.g. the Model finder's `finder_key` becomes a select
     * populated from the selected model's declared findBy columns).
     *
     * Return `null` to leave the static `settings()` shape alone, or
     * an array keyed by field name with the same shape as `settings()`
     * — only the listed fields are overridden, all other fields keep
     * their default schema.
     *
     * `PageBuilder::selectedNodeSchema()` merges this into the
     * library entry on every render, so the moment a dependent
     * setting flips the panel picks up the new shape.
     *
     * @param array<string, mixed> $node
     * @return array<string, array<string, mixed>>|null
     */
    public function dynamicSettings(array $node): ?array
    {
        return null;
    }

    /**
     * Return the config-shape the rest of the studio (palette renderer,
     * canvas, engine fallbacks) reads from `page-studio.nodes`. The
     * `class` key lets the engine round-trip back to the implementation.
     */
    public static function toLibraryEntry(): array
    {
        return [
            'group'    => static::group(),
            'label'    => static::label(),
            'icon'     => static::icon(),
            'inputs'   => static::inputs(),
            'outputs'  => static::outputs(),
            'settings' => static::settings(),
            'custom'   => true,
            'class'    => static::class,
        ];
    }
}
