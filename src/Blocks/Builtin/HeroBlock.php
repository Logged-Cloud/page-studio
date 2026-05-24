<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Hero · prominent banner-style block with a heading, supporting copy, an
 * optional image, and a CTA link. Common for marketing pages and the top
 * of a long-form email.
 */
class HeroBlock extends BlockType
{
    public static function key(): string   { return 'hero'; }
    public static function label(): string { return 'Hero'; }
    public static function icon(): string  { return '★'; }
    public static function group(): string { return 'layout'; }

    public static function settings(): array
    {
        return [
            'heading'   => ['kind' => 'text', 'label' => 'Heading',   'default' => 'Build something people want'],
            'subheading'=> ['kind' => 'textarea', 'label' => 'Subheading', 'default' => 'A two-sentence pitch goes here.'],
            'image'     => ['kind' => 'url',  'label' => 'Image URL', 'default' => ''],
            'cta_label' => ['kind' => 'text', 'label' => 'Button label', 'default' => 'Get started'],
            'cta_href'  => ['kind' => 'url',  'label' => 'Button link',  'default' => '#'],
            'align'     => [
                'kind'    => 'select',
                'label'   => 'Align',
                'default' => 'left',
                'options' => ['left' => 'Left', 'center' => 'Centre'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $heading    = PageRenderer::renderText((string) ($settings['heading']    ?? ''), $context, $decorate);
        $subheading = PageRenderer::renderText((string) ($settings['subheading'] ?? ''), $context, $decorate);
        $image      = htmlspecialchars(PageRenderer::substitute((string) ($settings['image'] ?? ''), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label      = PageRenderer::renderText((string) ($settings['cta_label'] ?? ''), $context, $decorate);
        $href       = htmlspecialchars(PageRenderer::substitute((string) ($settings['cta_href'] ?? '#'), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $align      = in_array($settings['align'] ?? 'left', ['left','center'], true) ? ($settings['align'] ?? 'left') : 'left';

        return '<section style="margin:1em 0;text-align:'.$align.'">'
            .($image    !== '' ? '<img src="'.$image.'" alt="" style="max-width:100%;height:auto;display:block;margin:0 auto .9em">' : '')
            .($heading  !== '' ? '<h1 style="font-size:1.6em;margin:.2em 0;line-height:1.2">'.$heading.'</h1>' : '')
            .($subheading !== '' ? '<p style="font-size:1.05em;color:#475569;margin:.4em 0 1em;line-height:1.55">'.$subheading.'</p>' : '')
            .($label    !== '' ? '<a href="'.$href.'" style="display:inline-block;background:var(--accent,#2C66E8);color:#fff;padding:.7rem 1.4rem;border-radius:.4rem;text-decoration:none;font-weight:600">'.$label.'</a>' : '')
            .'</section>';
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $heading    = PageRenderer::renderText((string) ($settings['heading']    ?? ''), $context, false);
        $subheading = PageRenderer::renderText((string) ($settings['subheading'] ?? ''), $context, false);
        $image      = htmlspecialchars(PageRenderer::substitute((string) ($settings['image'] ?? ''), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label      = PageRenderer::renderText((string) ($settings['cta_label'] ?? ''), $context, false);
        $href       = htmlspecialchars(PageRenderer::substitute((string) ($settings['cta_href'] ?? '#'), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $align      = in_array($settings['align'] ?? 'left', ['left','center'], true) ? ($settings['align'] ?? 'left') : 'left';

        $button = $label !== ''
            ? '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="'.$align.'" style="margin:12px 0 0;border-collapse:collapse">'
                .'<tr><td bgcolor="#2C66E8" style="background:#2C66E8;border-radius:4px">'
                    .'<a href="'.$href.'" style="display:inline-block;padding:10px 18px;color:#ffffff;text-decoration:none;font-weight:600;font-family:-apple-system,system-ui,sans-serif">'.$label.'</a>'
                .'</td></tr>'
            .'</table>'
            : '';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0;border-collapse:collapse">'
            .'<tr><td align="'.$align.'" style="text-align:'.$align.';font-family:-apple-system,system-ui,sans-serif">'
                .($image      !== '' ? '<img src="'.$image.'" alt="" width="600" style="max-width:100%;height:auto;display:block;margin:0 auto 14px;border:0">' : '')
                .($heading    !== '' ? '<h1 style="font-size:24px;margin:4px 0;line-height:1.2;color:#0f172a">'.$heading.'</h1>' : '')
                .($subheading !== '' ? '<p style="font-size:16px;color:#475569;margin:8px 0 4px;line-height:1.5">'.$subheading.'</p>' : '')
                .$button
            .'</td></tr>'
        .'</table>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $heading    = PageRenderer::substitute((string) ($settings['heading']    ?? ''), $context);
        $subheading = PageRenderer::substitute((string) ($settings['subheading'] ?? ''), $context);
        $label      = PageRenderer::substitute((string) ($settings['cta_label']  ?? ''), $context);
        $href       = PageRenderer::substitute((string) ($settings['cta_href']   ?? ''), $context);

        $out = '';
        if ($heading    !== '') $out .= "# {$heading}\n\n";
        if ($subheading !== '') $out .= "{$subheading}\n\n";
        if ($label      !== '' && $href !== '') $out .= "{$label}: {$href}\n\n";
        return $out;
    }
}
