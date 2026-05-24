<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformFieldNode extends NodeType
{
    public static function key(): string   { return 'transform.field'; }
    public static function label(): string { return 'Read field'; }
    public static function icon(): string  { return '.'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['object' => ['label' => 'Object / model', 'type' => 'object']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Field value', 'type' => 'any']]; }

    public static function settings(): array
    {
        return [
            'field' => ['kind' => 'text', 'label' => 'Field name', 'default' => 'name'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::readField($inputs['object'] ?? null, (string) ($settings['field'] ?? ''))];
    }
}
