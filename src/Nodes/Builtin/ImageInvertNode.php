<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ImageInvertNode extends NodeType
{
    public static function key(): string   { return 'image.invert'; }
    public static function label(): string { return 'Invert colours'; }
    public static function icon(): string  { return '⇆'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return ['image' => ['label' => 'Image', 'type' => 'image']]; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'value' => ['kind' => 'number', 'label' => '0 = normal · 1 = inverted', 'default' => '1.0'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['image' => NodeHelpers::imageFilter($inputs['image'] ?? null, 'invert('.((float) ($settings['value'] ?? 1)).')')];
    }
}
