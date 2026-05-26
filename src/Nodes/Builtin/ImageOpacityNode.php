<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ImageOpacityNode extends NodeType
{
    public static function key(): string   { return 'image.opacity'; }
    public static function label(): string { return 'Opacity'; }
    public static function icon(): string  { return '○'; }
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
            'value' => ['kind' => 'number', 'label' => '0 = invisible · 1 = solid', 'default' => '.65'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $value = NodeHelpers::filterValue($inputs, $settings, 1);
        return ['image' => NodeHelpers::imageFilter($inputs['image'] ?? null, 'opacity('.$value.')')];
    }
}
