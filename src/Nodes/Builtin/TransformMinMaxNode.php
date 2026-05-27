<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Min / Max of two values · op-selectable so one node covers both.
 */
class TransformMinMaxNode extends NodeType
{
    public static function key(): string   { return 'transform.min_max'; }
    public static function label(): string { return 'Min / Max'; }
    public static function icon(): string  { return '⇅'; }
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
                'label'   => 'Op',
                'default' => 'min',
                'options' => ['min' => 'Min', 'max' => 'Max'],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a  = (float) ($inputs['a'] ?? 0);
        $b  = (float) ($inputs['b'] ?? 0);
        $op = (string) ($settings['op'] ?? 'min');
        return ['value' => $op === 'max' ? max($a, $b) : min($a, $b)];
    }
}
