<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;

class SpacerBlock extends BlockType
{
    public static function key(): string   { return 'spacer'; }
    public static function label(): string { return 'Spacer'; }
    public static function icon(): string  { return '↕'; }

    public static function settings(): array
    {
        return [
            'size' => ['kind' => 'select', 'label' => 'Size', 'default' => 'md',
                'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large']],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $height = match ($settings['size'] ?? 'md') {
            'sm' => '.75rem',
            'lg' => '3rem',
            'xl' => '5rem',
            default => '1.5rem',
        };
        return sprintf('<div style="height:%s" aria-hidden="true"></div>', $height);
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        return "\n";
    }
}
