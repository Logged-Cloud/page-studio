<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceFloatNode extends NodeType
{
    public static function key(): string   { return 'source.float'; }
    public static function label(): string { return 'Float'; }
    public static function icon(): string  { return '.5'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'int']]; } // socket type 'int' is the engine's numeric type

    public static function settings(): array
    {
        return ['value' => ['kind' => 'number', 'label' => 'Value', 'default' => 0]];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (float) ($settings['value'] ?? 0)];
    }
}
