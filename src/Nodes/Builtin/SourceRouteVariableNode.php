<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceRouteVariableNode extends NodeType
{
    public static function key(): string   { return 'source.route_variable'; }
    public static function label(): string { return 'Route variable'; }
    public static function icon(): string  { return '⇥'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'any']]; }

    public static function settings(): array
    {
        return [
            'variable_name' => ['kind' => 'text', 'label' => 'Variable name', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $name = trim((string) ($settings['variable_name'] ?? ''));
        return ['value' => $context[$name] ?? null];
    }
}
