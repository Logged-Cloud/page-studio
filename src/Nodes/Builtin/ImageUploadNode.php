<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class ImageUploadNode extends NodeType
{
    public static function key(): string   { return 'image.upload'; }
    public static function label(): string { return 'Image upload'; }
    public static function icon(): string  { return '⬆'; }
    public static function group(): string { return 'image'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['image' => ['label' => 'Image', 'type' => 'image']]; }

    public static function settings(): array
    {
        return [
            'url' => ['kind' => 'upload', 'label' => 'Upload an image', 'default' => ''],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['image' => ['url' => (string) ($settings['url'] ?? ''), 'filter' => '']];
    }
}
