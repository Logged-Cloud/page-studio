<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * A constant color · the visual-graph analogue of Blender's "RGB"
 * input node. Outputs whatever the author picks in the color setting
 * so it can be piped into image-tint / image-solid / hue-rotate /
 * etc. without forcing the author to retype the same hex twice.
 */
class SourceColorNode extends NodeType
{
    public static function key(): string   { return 'source.color'; }
    public static function label(): string { return 'Color'; }
    public static function icon(): string  { return '🎨'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['color' => ['label' => 'Color', 'type' => 'color']]; }

    public static function settings(): array
    {
        return [
            'color' => ['kind' => 'color', 'label' => 'Color', 'default' => '#2C66E8'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['color' => (string) ($settings['color'] ?? '#000000')];
    }
}
