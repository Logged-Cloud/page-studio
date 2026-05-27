<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * XYZ vector constant · Blender's equivalent in the Constants
 * group. Output is an associative array, matches the shape the
 * rest of the engine uses for compound values.
 */
class SourceVectorNode extends NodeType
{
    public static function key(): string   { return 'source.vector'; }
    public static function label(): string { return 'Vector'; }
    public static function icon(): string  { return '➤'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'XYZ', 'type' => 'array']]; }

    public static function settings(): array
    {
        return [
            'x' => ['kind' => 'number', 'label' => 'X', 'default' => 0],
            'y' => ['kind' => 'number', 'label' => 'Y', 'default' => 0],
            'z' => ['kind' => 'number', 'label' => 'Z', 'default' => 0],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => [
            'x' => (float) ($settings['x'] ?? 0),
            'y' => (float) ($settings['y'] ?? 0),
            'z' => (float) ($settings['z'] ?? 0),
        ]];
    }
}
