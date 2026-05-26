<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ImageContrastNode extends NodeType
{
    public static function key(): string   { return 'image.contrast'; }
    public static function label(): string { return 'Contrast'; }
    public static function icon(): string  { return '◐'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array
    {
        return [
            'image' => ['label' => 'Image',                  'type' => 'image'],
            'value' => ['label' => 'Amount (numeric input)', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'value' => ['kind' => 'number', 'label' => 'Contrast (1.0 = normal)', 'default' => '1.0'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $value = NodeHelpers::filterValue($inputs, $settings, 1);
        return ['image' => NodeHelpers::imageFilter($inputs['image'] ?? null, 'contrast('.$value.')')];
    }
}
