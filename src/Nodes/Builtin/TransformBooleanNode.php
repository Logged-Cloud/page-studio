<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Boolean logic · AND / OR / XOR (binary) + NOT (unary, ignores B).
 */
class TransformBooleanNode extends NodeType
{
    public static function key(): string   { return 'transform.boolean'; }
    public static function label(): string { return 'Boolean'; }
    public static function icon(): string  { return '∧'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a' => ['label' => 'A', 'type' => 'bool'],
            'b' => ['label' => 'B', 'type' => 'bool'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'bool']]; }

    public static function settings(): array
    {
        return [
            'op' => [
                'kind'    => 'select',
                'label'   => 'Op',
                'default' => 'and',
                'options' => ['and' => 'AND', 'or' => 'OR', 'not' => 'NOT (A)', 'xor' => 'XOR'],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a  = (bool) ($inputs['a'] ?? false);
        $b  = (bool) ($inputs['b'] ?? false);
        $op = (string) ($settings['op'] ?? 'and');
        return ['value' => match ($op) {
            'or'  => $a || $b,
            'not' => ! $a,
            'xor' => $a xor $b,
            default => $a && $b,
        }];
    }
}
