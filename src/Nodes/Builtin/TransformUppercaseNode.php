<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformUppercaseNode extends NodeType
{
    public static function key(): string   { return 'transform.uppercase'; }
    public static function label(): string { return 'Uppercase'; }
    public static function icon(): string  { return 'A'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Uppercased', 'type' => 'string']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => mb_strtoupper((string) ($inputs['text'] ?? ''))];
    }
}
