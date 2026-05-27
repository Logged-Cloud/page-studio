<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Numeric comparison · A op B returns a bool. Op-selectable so one
 * node covers <, ≤, =, ≠, ≥, >.
 */
class TransformCompareNode extends NodeType
{
    public static function key(): string   { return 'transform.compare'; }
    public static function label(): string { return 'Compare'; }
    public static function icon(): string  { return '⋚'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a' => ['label' => 'A', 'type' => 'int'],
            'b' => ['label' => 'B', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'A op B', 'type' => 'bool']]; }

    public static function settings(): array
    {
        return [
            'op' => [
                'kind'    => 'select',
                'label'   => 'Operator',
                'default' => '=',
                'options' => ['<' => '<', '<=' => '≤', '=' => '=', '!=' => '≠', '>=' => '≥', '>' => '>'],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a = (float) ($inputs['a'] ?? 0);
        $b = (float) ($inputs['b'] ?? 0);
        $op = (string) ($settings['op'] ?? '=');
        return ['value' => match ($op) {
            '<'  => $a <  $b,
            '<=' => $a <= $b,
            '>'  => $a >  $b,
            '>=' => $a >= $b,
            '!=' => $a != $b,
            default => $a == $b,
        }];
    }
}
