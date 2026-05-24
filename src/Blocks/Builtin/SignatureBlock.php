<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Email signature · the standard "Cheers, Charles \n Founder \n email \n phone"
 * block that closes a message. Renders as a small table for email safety.
 */
class SignatureBlock extends BlockType
{
    public static function key(): string   { return 'signature'; }
    public static function label(): string { return 'Signature'; }
    public static function icon(): string  { return '✎'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'signoff' => ['kind' => 'text',  'label' => 'Sign-off', 'default' => 'Cheers,'],
            'name'    => ['kind' => 'text',  'label' => 'Name',     'default' => 'Your Name'],
            'title'   => ['kind' => 'text',  'label' => 'Title',    'default' => 'Founder'],
            'email'   => ['kind' => 'text',  'label' => 'Email',    'default' => ''],
            'phone'   => ['kind' => 'text',  'label' => 'Phone',    'default' => ''],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $signoff = PageRenderer::renderText((string) ($settings['signoff'] ?? ''), $context, $decorate);
        $name    = PageRenderer::renderText((string) ($settings['name']    ?? ''), $context, $decorate);
        $title   = PageRenderer::renderText((string) ($settings['title']   ?? ''), $context, $decorate);
        $email   = PageRenderer::renderText((string) ($settings['email']   ?? ''), $context, $decorate);
        $phone   = PageRenderer::renderText((string) ($settings['phone']   ?? ''), $context, $decorate);

        return '<div style="margin:.85em 0;color:#374151;font-size:.9em;line-height:1.5">'
            .($signoff !== '' ? '<div style="margin-bottom:.4em">'.$signoff.'</div>' : '')
            .($name    !== '' ? '<div style="font-weight:600;color:#111">'.$name.'</div>' : '')
            .($title   !== '' ? '<div style="color:#6b7280">'.$title.'</div>' : '')
            .($email   !== '' ? '<div style="margin-top:.3em">'.$email.'</div>' : '')
            .($phone   !== '' ? '<div>'.$phone.'</div>' : '')
            .'</div>';
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $signoff = PageRenderer::renderText((string) ($settings['signoff'] ?? ''), $context, false);
        $name    = PageRenderer::renderText((string) ($settings['name']    ?? ''), $context, false);
        $title   = PageRenderer::renderText((string) ($settings['title']   ?? ''), $context, false);
        $email   = PageRenderer::renderText((string) ($settings['email']   ?? ''), $context, false);
        $phone   = PageRenderer::renderText((string) ($settings['phone']   ?? ''), $context, false);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:14px 0;border-collapse:collapse;font-family:-apple-system,system-ui,sans-serif;font-size:14px;color:#374151;line-height:1.5">'
            .($signoff !== '' ? '<tr><td style="padding-bottom:8px">'.$signoff.'</td></tr>' : '')
            .($name    !== '' ? '<tr><td style="font-weight:600;color:#111;font-size:15px">'.$name.'</td></tr>' : '')
            .($title   !== '' ? '<tr><td style="color:#6b7280">'.$title.'</td></tr>' : '')
            .($email   !== '' ? '<tr><td style="padding-top:6px">'.$email.'</td></tr>' : '')
            .($phone   !== '' ? '<tr><td>'.$phone.'</td></tr>' : '')
            .'</table>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $lines = array_filter([
            PageRenderer::substitute((string) ($settings['signoff'] ?? ''), $context),
            PageRenderer::substitute((string) ($settings['name']    ?? ''), $context),
            PageRenderer::substitute((string) ($settings['title']   ?? ''), $context),
            PageRenderer::substitute((string) ($settings['email']   ?? ''), $context),
            PageRenderer::substitute((string) ($settings['phone']   ?? ''), $context),
        ], fn ($l) => $l !== '');
        return implode("\n", $lines)."\n\n";
    }
}
