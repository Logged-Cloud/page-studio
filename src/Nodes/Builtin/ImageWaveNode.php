<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Sine-wave bands · Blender's Wave Texture in Bands + Sine mode.
 * Build a single sine-wave SVG path that fills the lower half of
 * the image and tile it horizontally · gives an organic two-tone
 * banded look without resorting to JS-side rasterisation.
 */
class ImageWaveNode extends NodeType
{
    public static function key(): string   { return 'image.wave'; }
    public static function label(): string { return 'Wave'; }
    public static function icon(): string  { return '〜'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'a'         => ['kind' => 'color',  'label' => 'Crest',        'default' => '#2C66E8'],
            'b'         => ['kind' => 'color',  'label' => 'Trough',       'default' => '#0E1116'],
            'frequency' => ['kind' => 'number', 'label' => 'Cycles',       'default' => 4],
            'amplitude' => ['kind' => 'number', 'label' => 'Amplitude (px)','default' => 28],
            'imgWidth'  => ['kind' => 'number', 'label' => 'Image w (px)', 'default' => 600],
            'imgHeight' => ['kind' => 'number', 'label' => 'Image h (px)', 'default' => 220],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $crest  = (string) ($settings['a']         ?? '#2C66E8');
        $trough = (string) ($settings['b']         ?? '#0E1116');
        $freq   = max(1, (int) ($settings['frequency'] ?? 4));
        $amp    = max(2, (int) ($settings['amplitude'] ?? 28));
        $imgW   = max(2, (int) ($settings['imgWidth']  ?? 600));
        $imgH   = max(2, (int) ($settings['imgHeight'] ?? 220));

        // Build the sine wave path · sample one point per 4 px so
        // the curve stays smooth without ballooning the SVG.
        $midY   = $imgH / 2;
        $step   = 4;
        $points = [];
        for ($x = 0; $x <= $imgW; $x += $step) {
            $y = $midY + sin(($x / $imgW) * $freq * 2 * M_PI) * $amp;
            $points[] = sprintf('%g,%g', $x, $y);
        }
        // Close the path along the bottom edge so the bottom half
        // can be filled with the trough color.
        $points[] = sprintf('%d,%d', $imgW, $imgH);
        $points[] = sprintf('%d,%d', 0,     $imgH);
        $d = 'M'.implode(' L', $points).' Z';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d">'
            .'<rect width="100%%" height="100%%" fill="%s"/>'
            .'<path d="%s" fill="%s"/>'
            .'</svg>',
            $imgW, $imgH, $crest, $d, $trough,
        );

        return ['image' => ['url' => 'data:image/svg+xml;utf8,'.rawurlencode($svg), 'filter' => '']];
    }
}
