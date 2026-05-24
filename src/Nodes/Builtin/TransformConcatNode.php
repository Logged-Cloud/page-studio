<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformConcatNode extends NodeType
{
    public static function key(): string   { return 'transform.concat'; }
    public static function label(): string { return 'Concatenate'; }
    public static function icon(): string  { return '+'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a' => ['label' => 'A', 'type' => 'string'],
            'b' => ['label' => 'B', 'type' => 'string'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'A + B', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'separator' => ['kind' => 'text', 'label' => 'Separator', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (string) ($inputs['a'] ?? '')
            .((string) ($settings['separator'] ?? ''))
            .(string) ($inputs['b'] ?? '')];
    }
}
