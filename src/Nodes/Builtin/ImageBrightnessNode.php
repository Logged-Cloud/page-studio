<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ImageBrightnessNode extends NodeType
{
    public static function key(): string   { return 'image.brightness'; }
    public static function label(): string { return 'Brightness'; }
    public static function icon(): string  { return '☀'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array
    {
        return [
            'image' => ['label' => 'Image', 'type' => 'image'],
            'value' => ['label' => 'Amount (1.0 = normal)', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'value' => ['kind' => 'number', 'label' => 'Brightness (1.0 = normal)', 'default' => '1.0'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $value = NodeHelpers::filterValue($inputs, $settings, 1);
        return ['image' => NodeHelpers::imageFilter($inputs['image'] ?? null, 'brightness('.$value.')')];
    }
}
