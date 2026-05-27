<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Unary numeric op · round / floor / ceil / abs / sign. Mirrors
 * Blender's Math · Rounding + Type-Specific groups in one node.
 */
class TransformRoundNode extends NodeType
{
    public static function key(): string   { return 'transform.round'; }
    public static function label(): string { return 'Round'; }
    public static function icon(): string  { return '⌊⌉'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'int']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'int']]; }

    public static function settings(): array
    {
        return [
            'op' => [
                'kind'    => 'select',
                'label'   => 'Op',
                'default' => 'round',
                'options' => [
                    'round' => 'Round',
                    'floor' => 'Floor',
                    'ceil'  => 'Ceiling',
                    'abs'   => 'Absolute',
                    'sign'  => 'Sign',
                ],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $v  = (float) ($inputs['value'] ?? 0);
        $op = (string) ($settings['op'] ?? 'round');
        return ['value' => match ($op) {
            'floor' => floor($v),
            'ceil'  => ceil($v),
            'abs'   => abs($v),
            'sign'  => $v > 0 ? 1.0 : ($v < 0 ? -1.0 : 0.0),
            default => (float) round($v),
        }];
    }
}
