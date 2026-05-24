<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class PanelBlock extends BlockType
{
    public static function key(): string   { return 'panel'; }
    public static function label(): string { return 'Panel'; }
    public static function icon(): string  { return '⊡'; }
    public static function group(): string { return 'layout'; }

    public static function slots(): array
    {
        return ['body' => ['label' => 'Body']];
    }

    public static function settings(): array
    {
        return [
            'border' => [
                'kind'    => 'select',
                'label'   => 'Border',
                'default' => 'solid',
                'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'none' => 'None'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $border = match ($settings['border'] ?? 'solid') {
            'dashed' => '1px dashed #d1d5db',
            'none'   => 'none',
            default  => '1px solid #d1d5db',
        };
        $body = PageRenderer::renderChildren($children, 'body', $context, $decorate);
        return sprintf(
            '<div style="border:%s;border-radius:.4rem;padding:1rem 1.1rem;margin:.65em 0">%s</div>',
            $border, $body,
        );
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        return PageRenderer::renderChildrenForText($children, 'body', $context);
    }
}
