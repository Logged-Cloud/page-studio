<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ColumnsThreeBlock extends BlockType
{
    public static function key(): string   { return 'columns-3'; }
    public static function label(): string { return '3 Columns'; }
    public static function icon(): string  { return '⫼'; }
    public static function group(): string { return 'layout'; }

    public static function slots(): array
    {
        return ['left' => ['label' => 'Left'], 'middle' => ['label' => 'Middle'], 'right' => ['label' => 'Right']];
    }

    public static function settings(): array
    {
        return [
            'gap' => [
                'kind'    => 'select',
                'label'   => 'Gap',
                'default' => 'md',
                'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $gap = match ($settings['gap'] ?? 'md') { 'sm' => '.5rem', 'lg' => '2rem', default => '1.25rem' };

        $left   = PageRenderer::renderChildren($children, 'left',   $context, $decorate);
        $middle = PageRenderer::renderChildren($children, 'middle', $context, $decorate);
        $right  = PageRenderer::renderChildren($children, 'right',  $context, $decorate);

        return sprintf(
            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:%s;margin:.65em 0">'
                .'<div>%s</div><div>%s</div><div>%s</div></div>',
            $gap, $left, $middle, $right,
        );
    }
}
