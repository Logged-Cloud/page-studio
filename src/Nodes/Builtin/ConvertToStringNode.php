<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ConvertToStringNode extends NodeType
{
    public static function key(): string   { return 'convert.to_string'; }
    public static function label(): string { return 'To string'; }
    public static function icon(): string  { return '⇒"'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'String', 'type' => 'string']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::toString($inputs['value'] ?? null)];
    }
}
