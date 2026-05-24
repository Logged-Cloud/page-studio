<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class CardBlock extends BlockType
{
    public static function key(): string   { return 'card'; }
    public static function label(): string { return 'Card'; }
    public static function icon(): string  { return '⬜'; }
    public static function group(): string { return 'layout'; }
    // `renderEmail()` below emits a table-based layout that Outlook + Gmail
    // honour cleanly, so the block stays in the email-mode palette.
    public static function emailSafe(): bool { return true; }

    public static function slots(): array
    {
        return ['body' => ['label' => 'Body']];
    }

    public static function settings(): array
    {
        return [
            'title'    => ['kind' => 'text', 'label' => 'Title',    'default' => 'Card title'],
            'subtitle' => ['kind' => 'text', 'label' => 'Subtitle', 'default' => ''],
            'tone'     => [
                'kind'    => 'select',
                'label'   => 'Tone',
                'default' => 'neutral',
                'options' => ['neutral' => 'Neutral', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $tones = [
            'info'    => ['bg' => '#eff6ff', 'border' => '#3b82f6'],
            'success' => ['bg' => '#f0fdf4', 'border' => '#22c55e'],
            'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b'],
        ];
        $palette = $tones[$settings['tone'] ?? 'neutral'] ?? ['bg' => '#f9fafb', 'border' => '#d1d5db'];

        $title    = PageRenderer::renderText((string) ($settings['title']    ?? ''), $context, $decorate);
        $subtitle = PageRenderer::renderText((string) ($settings['subtitle'] ?? ''), $context, $decorate);
        $body     = PageRenderer::renderChildren($children, 'body', $context, $decorate);

        return sprintf(
            '<div style="background:%s;border-left:4px solid %s;padding:.9rem 1.1rem;border-radius:.4rem;margin:.65em 0">'
                .'%s%s%s</div>',
            $palette['bg'], $palette['border'],
            $title    !== '' ? '<h3 style="margin:0 0 .25em;font-size:1rem">'.$title.'</h3>' : '',
            $subtitle !== '' ? '<p style="margin:0 0 .35em;color:#6b7280;font-size:.85em">'.$subtitle.'</p>' : '',
            $body     !== '' ? '<div>'.$body.'</div>' : '',
        );
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $tones = [
            'info'    => ['bg' => '#eff6ff', 'border' => '#3b82f6'],
            'success' => ['bg' => '#f0fdf4', 'border' => '#22c55e'],
            'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b'],
        ];
        $palette = $tones[$settings['tone'] ?? 'neutral'] ?? ['bg' => '#f9fafb', 'border' => '#d1d5db'];

        $title    = PageRenderer::renderText((string) ($settings['title']    ?? ''), $context, false);
        $subtitle = PageRenderer::renderText((string) ($settings['subtitle'] ?? ''), $context, false);
        $body     = PageRenderer::renderChildrenForEmail($children, 'body', $context, false);

        // Two-cell table · narrow first cell paints the accent stripe in
        // a way Outlook respects (border-left on a div is unreliable).
        return sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0;border-collapse:collapse">'
                .'<tr>'
                    .'<td width="4" bgcolor="%2$s" style="width:4px;background:%2$s">&nbsp;</td>'
                    .'<td bgcolor="%1$s" style="background:%1$s;padding:14px 18px">'
                        .'%3$s%4$s%5$s'
                    .'</td>'
                .'</tr>'
            .'</table>',
            $palette['bg'], $palette['border'],
            $title    !== '' ? '<h3 style="margin:0 0 4px;font-size:16px">'.$title.'</h3>' : '',
            $subtitle !== '' ? '<p style="margin:0 0 6px;color:#6b7280;font-size:13px">'.$subtitle.'</p>' : '',
            $body     !== '' ? $body : '',
        );
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $title    = PageRenderer::substitute((string) ($settings['title']    ?? ''), $context);
        $subtitle = PageRenderer::substitute((string) ($settings['subtitle'] ?? ''), $context);
        $body     = PageRenderer::renderChildrenForText($children, 'body', $context);

        $out = '';
        if ($title    !== '') $out .= "{$title}\n";
        if ($subtitle !== '') $out .= "{$subtitle}\n";
        if ($out !== '')      $out .= "\n";
        $out .= $body;
        return $out;
    }
}
