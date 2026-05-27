<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Offset-row brick pattern · two bricks per row offset by half the
 * brick width on alternate rows. Mortar gap between bricks via the
 * SVG pattern's background fill. Closest analogue to Blender's
 * Brick Texture without dragging in JS-side rendering.
 */
class ImageBrickNode extends NodeType
{
    public static function key(): string   { return 'image.brick'; }
    public static function label(): string { return 'Brick'; }
    public static function icon(): string  { return '▬'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'brick'       => ['kind' => 'color',  'label' => 'Brick',          'default' => '#8B4513'],
            'mortar'      => ['kind' => 'color',  'label' => 'Mortar',         'default' => '#222222'],
            'brickWidth'  => ['kind' => 'number', 'label' => 'Brick w (px)',   'default' => 60],
            'brickHeight' => ['kind' => 'number', 'label' => 'Brick h (px)',   'default' => 28],
            'mortarSize'  => ['kind' => 'number', 'label' => 'Mortar (px)',    'default' => 2],
            'imgWidth'    => ['kind' => 'number', 'label' => 'Image w (px)',   'default' => 600],
            'imgHeight'   => ['kind' => 'number', 'label' => 'Image h (px)',   'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $brick  = (string) ($settings['brick']  ?? '#8B4513');
        $mortar = (string) ($settings['mortar'] ?? '#222222');
        $bw     = max(2,  (int) ($settings['brickWidth']  ?? 60));
        $bh     = max(2,  (int) ($settings['brickHeight'] ?? 28));
        $m      = max(0,  (int) ($settings['mortarSize']  ?? 2));
        $imgW   = max(1,  (int) ($settings['imgWidth']    ?? 600));
        $imgH   = max(1,  (int) ($settings['imgHeight']   ?? 220));

        // Pattern is 2 rows tall to encode the half-brick offset.
        // Row 1 · two full bricks aligned at x=0,bw. Row 2 · the
        // brick straddles the pattern wrap by half its width, which
        // SVG patterns tile correctly.
        $pw      = $bw;
        $ph      = $bh * 2;
        $brickW  = $bw - $m;
        $brickH  = $bh - $m;
        $halfBw  = (int) round($bw / 2);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><pattern id="p" width="%d" height="%d" patternUnits="userSpaceOnUse">'
            // mortar background fills the whole tile
            .'<rect width="%d" height="%d" fill="%s"/>'
            // row 1 brick
            .'<rect x="0" y="0" width="%d" height="%d" fill="%s"/>'
            // row 2 brick offset by half + wraps via the negative x sibling
            .'<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>'
            .'<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>'
            .'</pattern></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#p)"/></svg>',
            $imgW, $imgH,
            $pw, $ph,
            $pw, $ph, $mortar,
            $brickW, $brickH, $brick,
            $halfBw, $bh, $brickW, $brickH, $brick,
            $halfBw - $bw, $bh, $brickW, $brickH, $brick,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
