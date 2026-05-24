<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceNowNode extends NodeType
{
    public static function key(): string   { return 'source.now'; }
    public static function label(): string { return 'Now (datetime)'; }
    public static function icon(): string  { return '🕒'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Datetime', 'type' => 'object']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => new \DateTimeImmutable()];
    }
}
