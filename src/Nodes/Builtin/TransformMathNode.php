<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformMathNode extends NodeType
{
    public static function key(): string   { return 'transform.math'; }
    public static function label(): string { return 'Math'; }
    public static function icon(): string  { return '∑'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a' => ['label' => 'A', 'type' => 'int'],
            'b' => ['label' => 'B', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'int']]; }

    public static function settings(): array
    {
        return [
            'op' => [
                'kind'    => 'select',
                'label'   => 'Operator',
                'default' => '+',
                'options' => ['+' => '+', '-' => '−', '*' => '×', '/' => '÷', '%' => 'mod'],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::math(
            (float) ($inputs['a'] ?? 0),
            (float) ($inputs['b'] ?? 0),
            (string) ($settings['op'] ?? '+'),
        )];
    }
}
