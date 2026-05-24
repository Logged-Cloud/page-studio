<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformLengthNode extends NodeType
{
    public static function key(): string   { return 'transform.length'; }
    public static function label(): string { return 'Length'; }
    public static function icon(): string  { return '#'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'String or array', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Length', 'type' => 'int']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => match (true) {
            is_string($inputs['value'] ?? null)             => mb_strlen($inputs['value']),
            is_array($inputs['value']  ?? null)             => count($inputs['value']),
            ($inputs['value'] ?? null) instanceof \Countable => count($inputs['value']),
            default => 0,
        }];
    }
}
