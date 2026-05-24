<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformEqualsNode extends NodeType
{
    public static function key(): string   { return 'transform.equals'; }
    public static function label(): string { return 'Equals?'; }
    public static function icon(): string  { return '='; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'a' => ['label' => 'A', 'type' => 'any'],
            'b' => ['label' => 'B', 'type' => 'any'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'A == B', 'type' => 'bool']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => ($inputs['a'] ?? null) == ($inputs['b'] ?? null)];
    }
}
