<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Two-stop linear gradient · the smallest procedural geometry node
 * in the family. Emits an inline SVG data URI so the rest of the
 * image pipeline (brightness, hue-rotate, blur, etc.) composes on
 * top of it. `from`, `to` and `angle` are settings-as-sockets ·
 * authors can wire a Constant / Color / Math into any of them.
 */
class ImageGradientNode extends NodeType
{
    public static function key(): string   { return 'image.gradient'; }
    public static function label(): string { return 'Gradient'; }
    public static function icon(): string  { return '◐'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'from'   => ['kind' => 'color',  'label' => 'From',          'default' => '#2C66E8'],
            'to'     => ['kind' => 'color',  'label' => 'To',            'default' => '#E11D48'],
            'angle'  => ['kind' => 'number', 'label' => 'Angle (deg)',   'default' => 90],
            'width'  => ['kind' => 'number', 'label' => 'Width (px)',    'default' => 600],
            'height' => ['kind' => 'number', 'label' => 'Height (px)',   'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $from   = (string) ($settings['from']   ?? '#2C66E8');
        $to     = (string) ($settings['to']     ?? '#E11D48');
        $angle  = (float)  ($settings['angle']  ?? 90);
        $width  = max(1, (int) ($settings['width']  ?? 600));
        $height = max(1, (int) ($settings['height'] ?? 220));

        // SVG linear gradients use (x1,y1)-(x2,y2). Convert angle (degrees,
        // 0 = right, 90 = down) into normalised stops.
        $rad = deg2rad($angle);
        $dx  = cos($rad);
        $dy  = sin($rad);
        $x1  = sprintf('%.3f', 0.5 - $dx / 2);
        $y1  = sprintf('%.3f', 0.5 - $dy / 2);
        $x2  = sprintf('%.3f', 0.5 + $dx / 2);
        $y2  = sprintf('%.3f', 0.5 + $dy / 2);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><linearGradient id="g" x1="%s" y1="%s" x2="%s" y2="%s">'
            .'<stop offset="0%%" stop-color="%s"/>'
            .'<stop offset="100%%" stop-color="%s"/>'
            .'</linearGradient></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#g)"/></svg>',
            $width, $height, $x1, $y1, $x2, $y2, $from, $to,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
