<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Alternating two-color stripes · a procedural pattern built on
 * SVG's <pattern> element. Stripe width and angle are settings-
 * sockets so authors can drive them with math (e.g. wire the URL
 * length into stripe width for a quick visual indicator).
 */
class ImageStripesNode extends NodeType
{
    public static function key(): string   { return 'image.stripes'; }
    public static function label(): string { return 'Stripes'; }
    public static function icon(): string  { return '☰'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'a'         => ['kind' => 'color',  'label' => 'Color A',        'default' => '#2C66E8'],
            'b'         => ['kind' => 'color',  'label' => 'Color B',        'default' => '#0E1116'],
            'width'     => ['kind' => 'number', 'label' => 'Stripe (px)',    'default' => 32],
            'angle'     => ['kind' => 'number', 'label' => 'Angle (deg)',    'default' => 45],
            'imgWidth'  => ['kind' => 'number', 'label' => 'Image w (px)',   'default' => 600],
            'imgHeight' => ['kind' => 'number', 'label' => 'Image h (px)',   'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a       = (string) ($settings['a']         ?? '#2C66E8');
        $b       = (string) ($settings['b']         ?? '#0E1116');
        $stripe  = max(1, (int) ($settings['width']     ?? 32));
        $angle   = (float)   ($settings['angle']     ?? 45);
        $imgW    = max(1, (int) ($settings['imgWidth']  ?? 600));
        $imgH    = max(1, (int) ($settings['imgHeight'] ?? 220));
        $period  = $stripe * 2;

        // Two filled rects inside a <pattern> · the rotate() on
        // patternTransform spins the entire stripe field.
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><pattern id="p" width="%d" height="%d" patternUnits="userSpaceOnUse" patternTransform="rotate(%g)">'
            .'<rect width="%d" height="%d" fill="%s"/>'
            .'<rect x="%d" width="%d" height="%d" fill="%s"/>'
            .'</pattern></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#p)"/></svg>',
            $imgW, $imgH,
            $period, $period, $angle,
            $stripe, $period, $a,
            $stripe, $stripe, $period, $b,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
