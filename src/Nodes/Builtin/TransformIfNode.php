<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformIfNode extends NodeType
{
    public static function key(): string   { return 'transform.if'; }
    public static function label(): string { return 'If / else'; }
    public static function icon(): string  { return '?'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'condition' => ['label' => 'Condition', 'type' => 'bool'],
            'then'      => ['label' => 'Then',      'type' => 'any'],
            'else'      => ['label' => 'Else',      'type' => 'any'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'any']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => ($inputs['condition'] ?? false) ? ($inputs['then'] ?? null) : ($inputs['else'] ?? null)];
    }
}
