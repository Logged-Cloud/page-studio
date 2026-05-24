<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformFormatDateNode extends NodeType
{
    public static function key(): string   { return 'transform.format_date'; }
    public static function label(): string { return 'Format date'; }
    public static function icon(): string  { return '🗓'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Date / datetime', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Formatted', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'format' => ['kind' => 'text', 'label' => 'Format', 'default' => 'Y-m-d'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::formatDate($inputs['value'] ?? null, (string) ($settings['format'] ?? 'Y-m-d'))];
    }
}
