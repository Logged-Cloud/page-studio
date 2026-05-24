<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class ImageSourceNode extends NodeType
{
    public static function key(): string   { return 'image.source'; }
    public static function label(): string { return 'Image source'; }
    public static function icon(): string  { return '🖼'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'url' => ['kind' => 'url', 'label' => 'Image URL', 'default' => 'https://placehold.co/200x140'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['image' => ['url' => (string) ($settings['url'] ?? ''), 'filter' => '']];
    }
}
