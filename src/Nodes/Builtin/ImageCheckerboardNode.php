<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Two-color checkerboard · classic procedural pattern, useful as a
 * background or as the seed for a tinted / hue-rotated branch.
 * Cell size + colors are settings-sockets so authors can wire any
 * upstream value.
 */
class ImageCheckerboardNode extends NodeType
{
    public static function key(): string   { return 'image.checkerboard'; }
    public static function label(): string { return 'Checkerboard'; }
    public static function icon(): string  { return '▦'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'a'         => ['kind' => 'color',  'label' => 'Color A',        'default' => '#2C66E8'],
            'b'         => ['kind' => 'color',  'label' => 'Color B',        'default' => '#0E1116'],
            'cell'      => ['kind' => 'number', 'label' => 'Cell (px)',      'default' => 24],
            'imgWidth'  => ['kind' => 'number', 'label' => 'Image w (px)',   'default' => 600],
            'imgHeight' => ['kind' => 'number', 'label' => 'Image h (px)',   'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a    = (string) ($settings['a']         ?? '#2C66E8');
        $b    = (string) ($settings['b']         ?? '#0E1116');
        $cell = max(1, (int) ($settings['cell']      ?? 24));
        $imgW = max(1, (int) ($settings['imgWidth']  ?? 600));
        $imgH = max(1, (int) ($settings['imgHeight'] ?? 220));
        $tile = $cell * 2;

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><pattern id="p" width="%d" height="%d" patternUnits="userSpaceOnUse">'
            .'<rect width="%d" height="%d" fill="%s"/>'
            .'<rect x="%d" y="%d" width="%d" height="%d" fill="%s"/>'
            .'<rect x="%d" width="%d" height="%d" fill="%s"/>'
            .'<rect y="%d" width="%d" height="%d" fill="%s"/>'
            .'</pattern></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#p)"/></svg>',
            $imgW, $imgH,
            $tile, $tile,
            // top-left + bottom-right A cells
            $cell, $cell, $a,
            $cell, $cell, $cell, $cell, $a,
            // top-right + bottom-left B cells
            $cell, $cell, $cell, $b,
            $cell, $cell, $cell, $b,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
