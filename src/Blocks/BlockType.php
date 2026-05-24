<?php

namespace LoggedCloud\PageStudio\Blocks;

/**
 * Developer-defined page block · drop a subclass into
 * `app/PageStudio/Blocks/` (or register it explicitly via
 * `BlockRegistry::register`) and it shows up in the page-builder
 * palette alongside the built-in content / layout blocks.
 */
abstract class BlockType
{
    /** Unique identifier · convention "block.<snake_name>" or a short slug like "callout". */
    abstract public static function key(): string;

    /** Label shown in the palette + tree views. */
    abstract public static function label(): string;

    /** Emoji / glyph for the palette button. */
    public static function icon(): string
    {
        return '◻︎';
    }

    /** Palette section · 'content' | 'layout' (layout blocks declare slots). */
    public static function group(): string
    {
        return 'content';
    }

    /**
     * Setting fields rendered in the right panel. Same shape as nodes:
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
     * Whether this block renders cleanly inside an email client. Defaults
     * to true · override and return false on blocks that depend on
     * `display: grid`, `color-mix`, JavaScript, or any other feature
     * Outlook / Gmail / Apple Mail won't honour. When the page-builder
     * mounts with `emailMode => true`, palettes hide blocks whose
     * `emailSafe()` returns false.
     */
    public static function emailSafe(): bool
    {
        return true;
    }

    /**
     * Named child slots for layout blocks · 'body', 'left', 'right', etc.
     * Empty for plain content blocks.
     *
     * @return array<string, array{label?: string}>
     */
    public static function slots(): array
    {
        return [];
    }

    /**
     * Render the block to HTML.
     *
     * @param array<string, mixed>                                   $settings
     * @param array<string, array<int, array<string, mixed>>>        $children   slot => list of child blocks
     * @param array<string, mixed>                                   $context    page variable context
     * @param bool                                                   $decorate   editor-canvas decoration mode
     */
    abstract public function render(array $settings, array $children, array $context, bool $decorate = false): string;

    /**
     * Email-specific renderer · return a nested-table HTML string here for
     * blocks whose default `render()` uses CSS features email clients
     * don't honour (grid, flex, color-mix). Return null to fall back to
     * the regular render. The page-builder calls this path via
     * `PageRenderer::renderForEmail($blocks, $context)`.
     */
    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        return null;
    }

    /**
     * Plain-text renderer · pairs with `PageRenderer::renderForText()` to
     * produce the text/plain half of a multipart email. Return null and
     * the walker falls back to stripping HTML from `render()` · override
     * when the default strip loses structure (lists, headings, tables).
     */
    public function renderText(array $settings, array $children, array $context): ?string
    {
        return null;
    }

    /**
     * Library entry · palette renderer + page builder schema lookup read
     * this exact shape from `config('page-studio.blocks')`.
     */
    public static function toLibraryEntry(): array
    {
        return [
            'group'       => static::group(),
            'label'       => static::label(),
            'icon'        => static::icon(),
            'settings'    => static::settings(),
            'slots'       => static::slots(),
            'email_safe'  => static::emailSafe(),
            'custom'      => true,
            'class'       => static::class,
        ];
    }
}
