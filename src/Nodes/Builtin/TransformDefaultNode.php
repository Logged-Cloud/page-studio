<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformDefaultNode extends NodeType
{
    public static function key(): string   { return 'transform.default'; }
    public static function label(): string { return 'Default when empty'; }
    public static function icon(): string  { return '∅'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Value', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Value or fallback', 'type' => 'any']]; }

    public static function settings(): array
    {
        return [
            'fallback' => ['kind' => 'text', 'label' => 'Fallback', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => (($inputs['value'] ?? null) === null || $inputs['value'] === '')
            ? (string) ($settings['fallback'] ?? '')
            : $inputs['value']];
    }
}
