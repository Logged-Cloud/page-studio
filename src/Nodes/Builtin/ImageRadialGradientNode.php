<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Two-stop radial gradient · the SVG counterpart to Blender's
 * Spherical gradient. Centre stops at `from`, fades to `to` at the
 * edge. Wirable colors so it composes with source.color + the
 * filter chain like every other procedural node.
 */
class ImageRadialGradientNode extends NodeType
{
    public static function key(): string   { return 'image.radial_gradient'; }
    public static function label(): string { return 'Radial gradient'; }
    public static function icon(): string  { return '⊙'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'from'   => ['kind' => 'color',  'label' => 'Centre',    'default' => '#FFFFFF'],
            'to'     => ['kind' => 'color',  'label' => 'Edge',      'default' => '#0E1116'],
            'width'  => ['kind' => 'number', 'label' => 'Width (px)','default' => 600],
            'height' => ['kind' => 'number', 'label' => 'Height (px)','default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $from   = (string) ($settings['from'] ?? '#FFFFFF');
        $to     = (string) ($settings['to']   ?? '#0E1116');
        $width  = max(1, (int) ($settings['width']  ?? 600));
        $height = max(1, (int) ($settings['height'] ?? 220));

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><radialGradient id="r" cx="50%%" cy="50%%" r="60%%">'
            .'<stop offset="0%%" stop-color="%s"/>'
            .'<stop offset="100%%" stop-color="%s"/>'
            .'</radialGradient></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#r)"/></svg>',
            $width, $height, $from, $to,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
