<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ListBlock extends BlockType
{
    public static function key(): string   { return 'list'; }
    public static function label(): string { return 'List'; }
    public static function icon(): string  { return '☰'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'items' => [
                'kind'    => 'textarea',
                'label'   => 'Items (one per line)',
                'default' => "First item\nSecond item\nThird item",
            ],
            'style' => [
                'kind'    => 'select',
                'label'   => 'Style',
                'default' => 'bullet',
                'options' => ['bullet' => 'Bulleted', 'number' => 'Numbered'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $items = (string) ($settings['items'] ?? '');
        $style = (string) ($settings['style'] ?? 'bullet');

        $lines = preg_split('/\r?\n/', trim($items)) ?: [];
        $lines = array_filter($lines, fn ($l) => trim($l) !== '');
        if (! $lines) return '';

        $tag = $style === 'number' ? 'ol' : 'ul';
        $html = "<$tag style=\"margin:.65em 0;padding-left:1.5em\">";
        foreach ($lines as $line) {
            $html .= '<li style="margin:.2em 0">'.PageRenderer::renderText($line, $context, $decorate).'</li>';
        }
        return $html."</$tag>";
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $lines = preg_split('/\r?\n/', trim((string) ($settings['items'] ?? ''))) ?: [];
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if (! $lines) return '';

        $numbered = ($settings['style'] ?? 'bullet') === 'number';
        $out = '';
        foreach ($lines as $i => $line) {
            $marker = $numbered ? ($i + 1).'.' : '-';
            $out .= "{$marker} ".PageRenderer::substitute(trim($line), $context)."\n";
        }
        return $out."\n";
    }
}
