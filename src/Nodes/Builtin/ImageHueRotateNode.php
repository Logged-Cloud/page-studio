<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ImageHueRotateNode extends NodeType
{
    public static function key(): string   { return 'image.hue_rotate'; }
    public static function label(): string { return 'Hue rotate'; }
    public static function icon(): string  { return '🌈'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return ['image' => ['label' => 'Image', 'type' => 'image']]; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'value' => ['kind' => 'number', 'label' => 'Degrees', 'default' => '90'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['image' => NodeHelpers::imageFilter($inputs['image'] ?? null, 'hue-rotate('.((float) ($settings['value'] ?? 0)).'deg)')];
    }
}
