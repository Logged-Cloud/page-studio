<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformJoinNode extends NodeType
{
    public static function key(): string   { return 'transform.join'; }
    public static function label(): string { return 'Join array'; }
    public static function icon(): string  { return '⋃'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['array' => ['label' => 'Array', 'type' => 'array']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Joined', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'separator' => ['kind' => 'text', 'label' => 'Separator', 'default' => ', '],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::joinArrayLike($inputs['array'] ?? null, (string) ($settings['separator'] ?? ', '))];
    }
}
