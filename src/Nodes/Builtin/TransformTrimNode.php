<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformTrimNode extends NodeType
{
    public static function key(): string   { return 'transform.trim'; }
    public static function label(): string { return 'Trim whitespace'; }
    public static function icon(): string  { return '⌐'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Trimmed', 'type' => 'string']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => is_scalar($inputs['text'] ?? null) ? trim((string) $inputs['text']) : null];
    }
}
