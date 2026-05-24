<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ColumnsBlock extends BlockType
{
    public static function key(): string   { return 'columns'; }
    public static function label(): string { return '2 Columns'; }
    public static function icon(): string  { return '⊟'; }
    public static function group(): string { return 'layout'; }
    // The default render uses CSS grid (poor email-client support) but
    // `renderEmail()` below emits a nested table that Outlook + Gmail
    // honour cleanly · safe to leave in the email-mode palette.
    public static function emailSafe(): bool { return true; }

    public static function slots(): array
    {
        return ['left' => ['label' => 'Left'], 'right' => ['label' => 'Right']];
    }

    public static function settings(): array
    {
        return [
            'ratio' => [
                'kind'    => 'select',
                'label'   => 'Ratio',
                'default' => '1-1',
                'options' => ['1-1' => '50 / 50', '1-2' => '33 / 67', '2-1' => '67 / 33'],
            ],
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
        $ratios = ['1-1' => '1fr 1fr', '1-2' => '1fr 2fr', '2-1' => '2fr 1fr'];
        $grid = $ratios[$settings['ratio'] ?? '1-1'] ?? '1fr 1fr';
        $gap  = match ($settings['gap'] ?? 'md') { 'sm' => '.5rem', 'lg' => '2rem', default => '1.25rem' };

        $left  = PageRenderer::renderChildren($children, 'left',  $context, $decorate);
        $right = PageRenderer::renderChildren($children, 'right', $context, $decorate);

        return sprintf(
            '<div style="display:grid;grid-template-columns:%s;gap:%s;margin:.65em 0">'
                .'<div>%s</div><div>%s</div></div>',
            $grid, $gap, $left, $right,
        );
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $ratios = [
            '1-1' => ['50%', '50%'],
            '1-2' => ['33%', '67%'],
            '2-1' => ['67%', '33%'],
        ];
        [$leftW, $rightW] = $ratios[$settings['ratio'] ?? '1-1'] ?? ['50%', '50%'];
        $gap = match ($settings['gap'] ?? 'md') { 'sm' => 4, 'lg' => 24, default => 12 };
        $half = (int) round($gap / 2);

        $left  = PageRenderer::renderChildrenForEmail($children, 'left',  $context, $decorate);
        $right = PageRenderer::renderChildrenForEmail($children, 'right', $context, $decorate);

        // Nested table · the canonical email-safe column layout. role +
        // border attrs neutralise Outlook + Apple Mail's default styling.
        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0;border-collapse:collapse">'
                .'<tr>'
                    .'<td width="%1$s" valign="top" style="padding-right:%3$dpx">%4$s</td>'
                    .'<td width="%2$s" valign="top" style="padding-left:%3$dpx">%5$s</td>'
                .'</tr>'
            .'</table>',
            $leftW, $rightW, $half, $left, $right,
        );
    }
}
