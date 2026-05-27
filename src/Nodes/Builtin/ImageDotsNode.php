<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Polka-dot grid · evenly-spaced circles on a coloured background.
 * Closest complement to image.checkerboard · same "tile a small
 * SVG pattern across a bigger rect" pattern. Useful as a hero
 * background, a CTA backdrop, or a tinted base for a filter chain.
 */
class ImageDotsNode extends NodeType
{
    public static function key(): string   { return 'image.dots'; }
    public static function label(): string { return 'Dots'; }
    public static function icon(): string  { return '⋮⋮'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'dot'        => ['kind' => 'color',  'label' => 'Dot',           'default' => '#FFFFFF'],
            'background' => ['kind' => 'color',  'label' => 'Background',    'default' => '#0E1116'],
            'radius'     => ['kind' => 'number', 'label' => 'Radius (px)',   'default' => 4],
            'spacing'    => ['kind' => 'number', 'label' => 'Spacing (px)',  'default' => 24],
            'imgWidth'   => ['kind' => 'number', 'label' => 'Image w (px)',  'default' => 600],
            'imgHeight'  => ['kind' => 'number', 'label' => 'Image h (px)',  'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $dot     = (string) ($settings['dot']        ?? '#FFFFFF');
        $bg      = (string) ($settings['background'] ?? '#0E1116');
        $r       = max(1, (int) ($settings['radius']     ?? 4));
        $space   = max(2, (int) ($settings['spacing']    ?? 24));
        $imgW    = max(1, (int) ($settings['imgWidth']   ?? 600));
        $imgH    = max(1, (int) ($settings['imgHeight']  ?? 220));

        $half = (int) ($space / 2);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<defs><pattern id="p" width="%d" height="%d" patternUnits="userSpaceOnUse">'
            .'<rect width="%d" height="%d" fill="%s"/>'
            .'<circle cx="%d" cy="%d" r="%d" fill="%s"/>'
            .'</pattern></defs>'
            .'<rect width="100%%" height="100%%" fill="url(#p)"/></svg>',
            $imgW, $imgH,
            $space, $space,
            $space, $space, $bg,
            $half, $half, $r, $dot,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
