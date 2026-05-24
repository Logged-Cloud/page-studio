<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use Illuminate\Support\Str;
use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformLowercaseNode extends NodeType
{
    public static function key(): string   { return 'transform.lowercase'; }
    public static function label(): string { return 'Lowercase'; }
    public static function icon(): string  { return 'a'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Lowercased', 'type' => 'string']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => is_scalar($inputs['text'] ?? null) ? Str::lower((string) $inputs['text']) : null];
    }
}
