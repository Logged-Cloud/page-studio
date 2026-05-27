<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Substring slice · take `length` chars from offset `start`. Both
 * are settings-sockets so authors can wire from upstream length /
 * math results.
 */
class TransformSubstringNode extends NodeType
{
    public static function key(): string   { return 'transform.substring'; }
    public static function label(): string { return 'Substring'; }
    public static function icon(): string  { return '⌑'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Slice', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'start'  => ['kind' => 'number', 'label' => 'Start',  'default' => 0],
            'length' => ['kind' => 'number', 'label' => 'Length', 'default' => 10],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $text   = (string) ($inputs['text']    ?? '');
        $start  = (int)    ($settings['start']  ?? 0);
        $length = (int)    ($settings['length'] ?? 10);
        return ['value' => mb_substr($text, $start, $length)];
    }
}
