<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ConvertToBoolNode extends NodeType
{
    public static function key(): string   { return 'convert.to_bool'; }
    public static function label(): string { return 'To boolean'; }
    public static function icon(): string  { return '⇒?'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Boolean', 'type' => 'bool']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::toBool($inputs['value'] ?? null)];
    }
}
