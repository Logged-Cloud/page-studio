<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ImageBlock extends BlockType
{
    public static function key(): string   { return 'image'; }
    public static function label(): string { return 'Image'; }
    public static function icon(): string  { return '🖼'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'src' => ['kind' => 'url',  'label' => 'Image URL', 'default' => 'https://placehold.co/600x300'],
            'alt' => ['kind' => 'text', 'label' => 'Alt text',  'default' => ''],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $rawSrc = (string) ($settings['src'] ?? '');
        $alt    = (string) ($settings['alt'] ?? '');

        if (preg_match('/^\s*\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}\s*$/', $rawSrc, $m)
            && is_array($context[$m[1]] ?? null)
            && isset($context[$m[1]]['url'])
        ) {
            $img    = $context[$m[1]];
            $url    = htmlspecialchars((string) $img['url'],            ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $filter = htmlspecialchars((string) ($img['filter'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $altE   = htmlspecialchars(PageRenderer::substitute($alt, $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<img src="'.$url.'" alt="'.$altE.'" '
                .'style="max-width:100%;height:auto;display:block;margin:.65em 0;'
                .($filter !== '' ? "filter:$filter" : '').'">';
        }

        $src  = htmlspecialchars(PageRenderer::substitute($rawSrc, $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $altE = htmlspecialchars(PageRenderer::substitute($alt,    $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return '<img src="'.$src.'" alt="'.$altE.'" style="max-width:100%;height:auto;display:block;margin:.65em 0">';
    }
}
