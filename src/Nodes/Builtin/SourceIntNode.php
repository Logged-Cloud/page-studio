<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceIntNode extends NodeType
{
    public static function key(): string   { return 'source.int'; }
    public static function label(): string { return 'Integer'; }
    public static function icon(): string  { return '#'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'int']]; }

    public static function settings(): array
    {
        return ['value' => ['kind' => 'number', 'label' => 'Value', 'default' => 0]];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (int) ($settings['value'] ?? 0)];
    }
}
