<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Clamp · pin a number into a [min, max] range. min/max are
 * settings-sockets so authors can wire from upstream nodes.
 */
class TransformClampNode extends NodeType
{
    public static function key(): string   { return 'transform.clamp'; }
    public static function label(): string { return 'Clamp'; }
    public static function icon(): string  { return '[ ]'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'value' => ['label' => 'Value', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Clamped', 'type' => 'int']]; }

    public static function settings(): array
    {
        return [
            'min' => ['kind' => 'number', 'label' => 'Min', 'default' => 0],
            'max' => ['kind' => 'number', 'label' => 'Max', 'default' => 1],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $v   = (float) ($inputs['value'] ?? 0);
        $min = (float) ($settings['min'] ?? 0);
        $max = (float) ($settings['max'] ?? 1);
        return ['value' => max($min, min($max, $v))];
    }
}
