<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformFirstNode extends NodeType
{
    public static function key(): string   { return 'transform.first'; }
    public static function label(): string { return 'First item'; }
    public static function icon(): string  { return '⏮'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['array' => ['label' => 'Array', 'type' => 'array']]; }
    public static function outputs(): array { return ['value' => ['label' => 'First',  'type' => 'any']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::firstOf($inputs['array'] ?? null)];
    }
}
