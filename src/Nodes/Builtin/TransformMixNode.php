<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Linear blend · a + (b - a) * factor. Factor expected in [0, 1]
 * but doesn't clamp · clamp upstream if you need it.
 */
class TransformMixNode extends NodeType
{
    public static function key(): string   { return 'transform.mix'; }
    public static function label(): string { return 'Mix'; }
    public static function icon(): string  { return '◐'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a'      => ['label' => 'A',      'type' => 'int'],
            'b'      => ['label' => 'B',      'type' => 'int'],
            'factor' => ['label' => 'Factor', 'type' => 'int'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'int']]; }

    public static function settings(): array
    {
        return ['factor' => ['kind' => 'number', 'label' => 'Factor (fallback)', 'default' => 0.5]];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $a = (float) ($inputs['a'] ?? 0);
        $b = (float) ($inputs['b'] ?? 0);
        $t = array_key_exists('factor', $inputs) && $inputs['factor'] !== null
            ? (float) $inputs['factor']
            : (float) ($settings['factor'] ?? 0.5);
        return ['value' => $a + ($b - $a) * $t];
    }
}
