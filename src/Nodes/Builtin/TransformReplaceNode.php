<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformReplaceNode extends NodeType
{
    public static function key(): string   { return 'transform.replace'; }
    public static function label(): string { return 'Replace text'; }
    public static function icon(): string  { return '⇄'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'find'    => ['kind' => 'text', 'label' => 'Find', 'default' => ''],
            'replace' => ['kind' => 'text', 'label' => 'Replace with', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => is_scalar($inputs['text'] ?? null)
            ? str_replace((string) ($settings['find'] ?? ''), (string) ($settings['replace'] ?? ''), (string) $inputs['text'])
            : null];
    }
}
