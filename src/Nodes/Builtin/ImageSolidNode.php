<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * A solid-color image · the graph-side analogue of a one-pixel
 * texture in Blender. Renders an inline SVG data URI so the image
 * block can drop it in as a `<img src="...">` without a round-trip
 * to disk, and the CSS-filter chain (brightness / hue-rotate / etc.)
 * still works downstream.
 *
 * Color is an INPUT so authors can wire a `source.color` (or any
 * other color-producing node) straight in. Static color setting acts
 * as the fallback when no input is connected.
 */
class ImageSolidNode extends NodeType
{
    public static function key(): string   { return 'image.solid'; }
    public static function label(): string { return 'Solid color image'; }
    public static function icon(): string  { return '▣'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array
    {
        return [
            'color' => ['label' => 'Color', 'type' => 'color'],
        ];
    }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'color'  => ['kind' => 'color',  'label' => 'Fallback color', 'default' => '#2C66E8'],
            'width'  => ['kind' => 'number', 'label' => 'Width (px)',     'default' => 800],
            'height' => ['kind' => 'number', 'label' => 'Height (px)',    'default' => 300],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $color  = (string) ($inputs['color'] ?? $settings['color'] ?? '#000000');
        $width  = max(1, (int) ($settings['width']  ?? 800));
        $height = max(1, (int) ($settings['height'] ?? 300));

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d"><rect width="100%%" height="100%%" fill="%s"/></svg>',
            $width, $height, $color,
        );
        $url = 'data:image/svg+xml;utf8,'.rawurlencode($svg);

        return ['image' => ['url' => $url, 'filter' => '']];
    }
}
