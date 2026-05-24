<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class SectionBlock extends BlockType
{
    public static function key(): string   { return 'section'; }
    public static function label(): string { return 'Section'; }
    public static function icon(): string  { return '▢'; }
    public static function group(): string { return 'layout'; }

    public static function slots(): array
    {
        return ['body' => ['label' => 'Body']];
    }

    public static function settings(): array
    {
        return [
            'background' => [
                'kind'    => 'select',
                'label'   => 'Background',
                'default' => 'none',
                'options' => ['none' => 'None', 'tint' => 'Tinted', 'accent' => 'Accent'],
            ],
            'padding' => [
                'kind'    => 'select',
                'label'   => 'Padding',
                'default' => 'md',
                'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $bg = match ($settings['background'] ?? 'none') {
            'tint'   => 'background:#f3f4f6;',
            'accent' => 'background:color-mix(in srgb, #2C66E8 8%, transparent);',
            default  => '',
        };
        $pad = match ($settings['padding'] ?? 'md') {
            'sm' => '.85rem 1rem',
            'lg' => '2rem 2.25rem',
            default => '1.25rem 1.5rem',
        };
        $body = PageRenderer::renderChildren($children, 'body', $context, $decorate);
        return sprintf(
            '<section style="%spadding:%s;border-radius:.4rem;margin:.65em 0">%s</section>',
            $bg, $pad, $body,
        );
    }
}
