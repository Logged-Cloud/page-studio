<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class ConvertToArrayNode extends NodeType
{
    public static function key(): string   { return 'convert.to_array'; }
    public static function label(): string { return 'To array'; }
    public static function icon(): string  { return '⇒[]'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Array', 'type' => 'array']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::toArray($inputs['value'] ?? null)];
    }
}
