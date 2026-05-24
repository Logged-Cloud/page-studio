<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformSplitNode extends NodeType
{
    public static function key(): string   { return 'transform.split'; }
    public static function label(): string { return 'Split to array'; }
    public static function icon(): string  { return '⫝'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Parts', 'type' => 'array']]; }

    public static function settings(): array
    {
        return [
            'delimiter' => ['kind' => 'text', 'label' => 'Delimiter', 'default' => ','],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => is_scalar($inputs['text'] ?? null)
            ? explode((string) ($settings['delimiter'] ?? ','), (string) $inputs['text'])
            : []];
    }
}
