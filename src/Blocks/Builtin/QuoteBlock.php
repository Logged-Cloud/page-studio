<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class QuoteBlock extends BlockType
{
    public static function key(): string   { return 'quote'; }
    public static function label(): string { return 'Quote'; }
    public static function icon(): string  { return '❝'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'text' => ['kind' => 'textarea', 'label' => 'Quote',  'default' => 'A pithy thing someone said.'],
            'cite' => ['kind' => 'text',     'label' => 'Source', 'default' => ''],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $text = PageRenderer::renderText((string) ($settings['text'] ?? ''), $context, $decorate);
        $cite = (string) ($settings['cite'] ?? '');
        return '<blockquote style="margin:.85em 0;padding:.4em 1em;border-left:4px solid #d0d5dd;color:#555;font-style:italic">'
            .$text
            .($cite !== '' ? '<footer style="margin-top:.35em;font-size:.85em;color:#666;font-style:normal">— '.PageRenderer::renderText($cite, $context, $decorate).'</footer>' : '')
            .'</blockquote>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $text = PageRenderer::substitute((string) ($settings['text'] ?? ''), $context);
        $cite = (string) ($settings['cite'] ?? '');
        $body = "> ".str_replace("\n", "\n> ", $text)."\n";
        if ($cite !== '') {
            $body .= "  - ".PageRenderer::substitute($cite, $context)."\n";
        }
        return $body."\n";
    }
}
