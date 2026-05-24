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
    // The default render uses CSS grid; `renderEmail()` below emits a
    // nested table so the block also works in email contexts.
    public static function emailSafe(): bool { return true; }

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

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $gap  = match ($settings['gap'] ?? 'md') { 'sm' => 4, 'lg' => 24, default => 12 };
        $half = (int) round($gap / 2);

        $left   = PageRenderer::renderChildrenForEmail($children, 'left',   $context, $decorate);
        $middle = PageRenderer::renderChildrenForEmail($children, 'middle', $context, $decorate);
        $right  = PageRenderer::renderChildrenForEmail($children, 'right',  $context, $decorate);

        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0;border-collapse:collapse">'
                .'<tr>'
                    .'<td width="33%%" valign="top" style="padding-right:%1$dpx">%2$s</td>'
                    .'<td width="34%%" valign="top" style="padding:0 %1$dpx">%3$s</td>'
                    .'<td width="33%%" valign="top" style="padding-left:%1$dpx">%4$s</td>'
                .'</tr>'
            .'</table>',
            $half, $left, $middle, $right,
        );
    }
}
