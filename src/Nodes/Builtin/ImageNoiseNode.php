<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Fractal-noise texture · uses SVG's <feTurbulence> primitive to
 * draw a deterministic noise field. Useful as a base layer for
 * tint / blend / hue-rotate stacks. Seed + scale are settings-
 * sockets so authors can animate the seed (e.g. drive from a
 * route-variable) for randomised hero backgrounds.
 */
class ImageNoiseNode extends NodeType
{
    public static function key(): string   { return 'image.noise'; }
    public static function label(): string { return 'Noise'; }
    public static function icon(): string  { return '∷'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'seed'      => ['kind' => 'number', 'label' => 'Seed',          'default' => 1],
            'scale'     => ['kind' => 'number', 'label' => 'Base freq',     'default' => 0.65],
            'octaves'   => ['kind' => 'number', 'label' => 'Octaves',       'default' => 2],
            'imgWidth'  => ['kind' => 'number', 'label' => 'Image w (px)',  'default' => 600],
            'imgHeight' => ['kind' => 'number', 'label' => 'Image h (px)',  'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $seed    = (int) ($settings['seed']     ?? 1);
        $scale   = (float) ($settings['scale']  ?? 0.65);
        $oct     = max(1, (int) ($settings['octaves']   ?? 2));
        $imgW    = max(1, (int) ($settings['imgWidth']  ?? 600));
        $imgH    = max(1, (int) ($settings['imgHeight'] ?? 220));

        // feTurbulence with `numOctaves` + `seed` is deterministic ·
        // same params produce the same noise. The outer rect just
        // gives the filter a surface to paint on.
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<filter id="n">'
            .'<feTurbulence type="fractalNoise" baseFrequency="%g" numOctaves="%d" seed="%d"/>'
            .'</filter>'
            .'<rect width="100%%" height="100%%" filter="url(#n)"/></svg>',
            $imgW, $imgH,
            $scale, $oct, $seed,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
