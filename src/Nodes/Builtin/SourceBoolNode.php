<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceBoolNode extends NodeType
{
    public static function key(): string   { return 'source.bool'; }
    public static function label(): string { return 'Boolean'; }
    public static function icon(): string  { return '☐'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'bool']]; }

    public static function settings(): array
    {
        return ['value' => ['kind' => 'bool', 'label' => 'Value', 'default' => false]];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (bool) ($settings['value'] ?? false)];
    }
}
