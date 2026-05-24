<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceConstantNode extends NodeType
{
    public static function key(): string   { return 'source.constant'; }
    public static function label(): string { return 'Constant'; }
    public static function icon(): string  { return '”'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'value' => ['kind' => 'text', 'label' => 'Value', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (string) ($settings['value'] ?? '')];
    }
}
