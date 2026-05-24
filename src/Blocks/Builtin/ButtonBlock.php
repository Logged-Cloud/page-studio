<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ButtonBlock extends BlockType
{
    public static function key(): string   { return 'button'; }
    public static function label(): string { return 'Button'; }
    public static function icon(): string  { return '▭'; }

    public static function settings(): array
    {
        return [
            'label'   => ['kind' => 'text', 'label' => 'Label', 'default' => 'Call to action'],
            'href'    => ['kind' => 'url',  'label' => 'Link',  'default' => '#'],
            'variant' => ['kind' => 'select', 'label' => 'Variant', 'default' => 'primary',
                'options' => ['primary' => 'Primary', 'secondary' => 'Secondary', 'ghost' => 'Ghost']],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $variant = (string) ($settings['variant'] ?? 'primary');
        $href = htmlspecialchars(
            PageRenderer::substitute((string) ($settings['href'] ?? '#'), $context, false),
            ENT_QUOTES | ENT_HTML5, 'UTF-8',
        );
        $label = PageRenderer::renderText((string) ($settings['label'] ?? ''), $context, $decorate);
        $style = match ($variant) {
            'secondary' => 'display:inline-block;background:transparent;border:1px solid var(--line,#3A3D40);color:var(--ink,#F0EDE5);padding:.55rem 1.1rem;border-radius:.35rem;text-decoration:none',
            'ghost'     => 'display:inline-block;background:transparent;border:0;color:var(--accent,#2C66E8);padding:.55rem 1.1rem;text-decoration:none',
            default     => 'display:inline-block;background:var(--accent,#2C66E8);color:#fff;padding:.55rem 1.1rem;border-radius:.35rem;text-decoration:none',
        };

        return sprintf(
            '<a href="%s" class="ps-render-btn ps-render-btn--%s" style="%s">%s</a>',
            $href,
            htmlspecialchars($variant, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $style,
            $label,
        );
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        // Outlook is the only major client that mangles inline-block anchors ·
        // wrap in a single-cell table so the touch target is honoured everywhere.
        $variant = (string) ($settings['variant'] ?? 'primary');
        $href    = htmlspecialchars(
            PageRenderer::substitute((string) ($settings['href'] ?? '#'), $context, false),
            ENT_QUOTES | ENT_HTML5, 'UTF-8',
        );
        $label = PageRenderer::renderText((string) ($settings['label'] ?? ''), $context, false);

        [$bg, $fg, $border] = match ($variant) {
            'secondary' => ['#ffffff', '#1a1a1a', '#d0d5dd'],
            'ghost'     => ['#ffffff', '#2C66E8', '#ffffff'],
            default     => ['#2C66E8', '#ffffff', '#2C66E8'],
        };

        return sprintf(
            '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0;border-collapse:collapse">'
                .'<tr>'
                    .'<td bgcolor="%1$s" style="background:%1$s;border:1px solid %3$s;border-radius:4px">'
                        .'<a href="%4$s" style="display:inline-block;padding:10px 18px;color:%2$s;text-decoration:none;font-weight:600;font-family:-apple-system,system-ui,sans-serif">%5$s</a>'
                    .'</td>'
                .'</tr>'
            .'</table>',
            $bg, $fg, $border, $href, $label,
        );
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $label = PageRenderer::substitute((string) ($settings['label'] ?? ''), $context);
        $href  = PageRenderer::substitute((string) ($settings['href']  ?? '#'), $context);
        return "{$label}: {$href}\n\n";
    }

    public function renderMarkdown(array $settings, array $children, array $context): ?string
    {
        $label = PageRenderer::substitute((string) ($settings['label'] ?? ''), $context);
        $href  = PageRenderer::substitute((string) ($settings['href']  ?? '#'), $context);
        return "[{$label}]({$href})\n\n";
    }
}
