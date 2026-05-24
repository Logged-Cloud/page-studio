<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class ConvertToIntNode extends NodeType
{
    public static function key(): string   { return 'convert.to_int'; }
    public static function label(): string { return 'To integer'; }
    public static function icon(): string  { return '⇒#'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Integer', 'type' => 'int']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (int) ($inputs['value'] ?? 0)];
    }
}
