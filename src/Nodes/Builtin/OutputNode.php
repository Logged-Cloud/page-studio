<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class OutputNode extends NodeType
{
    public static function key(): string   { return 'output'; }
    public static function label(): string { return 'Output variable'; }
    public static function icon(): string  { return '▶'; }
    public static function group(): string { return 'output'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return []; }

    public static function settings(): array
    {
        return ['name' => ['kind' => 'text', 'label' => 'Variable name', 'default' => 'newVar']];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        // Symmetry with engine fallback · downstream introspection reads `value`.
        return ['value' => $inputs['value'] ?? null];
    }
}
