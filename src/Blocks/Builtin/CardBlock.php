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
    // The tinted background uses `color-mix(in srgb, ...)` which Gmail and
    // older Outlook strip · the result is a flat white box. Hide from
    // email-mode palettes.
    public static function emailSafe(): bool { return false; }

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
}
