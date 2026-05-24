<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Tabular content · stores the raw `<table>...</table>` markup so authors
 * who migrate from a wysiwyg editor keep their structure verbatim. Tokens
 * inside the HTML (`{{ name }}` or `{{ user.email }}`) still substitute at
 * render time. Editing is via a textarea in the right panel for now;
 * structured row / column editing is a future iteration.
 */
class TableBlock extends BlockType
{
    public static function key(): string   { return 'table'; }
    public static function label(): string { return 'Table'; }
    public static function icon(): string  { return '⊞'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'html' => [
                'kind'    => 'textarea',
                'label'   => 'Table HTML',
                'default' => "<table>\n  <tr><th>Header</th><th>Header</th></tr>\n  <tr><td>Cell</td><td>Cell</td></tr>\n</table>",
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $html = (string) ($settings['html'] ?? '');
        // Substitute `{{ tokens }}` against the context · authors are trusted
        // (the editor sits behind the page-studio.gate), so we don't escape
        // the surrounding markup.
        return PageRenderer::substitute($html, $context, false);
    }
}
