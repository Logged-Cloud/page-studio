<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Accordion · stack of expandable Q/A items. Web render uses native
 * <details>/<summary> for zero-JS interactivity; email render flattens to
 * stacked bold-title + paragraph since <details> is mail-client unsupported.
 */
class AccordionBlock extends BlockType
{
    public static function key(): string   { return 'accordion'; }
    public static function label(): string { return 'Accordion'; }
    public static function icon(): string  { return '▾'; }
    public static function group(): string { return 'content'; }
    public static function emailSafe(): bool { return true; }

    public static function settings(): array
    {
        return [
            'items' => [
                'kind'    => 'textarea',
                'label'   => 'Items (one Title|Body per line)',
                'default' => "Question one?|Answer one.\nQuestion two?|Answer two.",
            ],
            'expanded' => [
                'kind'    => 'select',
                'label'   => 'Initially expanded',
                'default' => 'first',
                'options' => ['all' => 'All', 'none' => 'None', 'first' => 'First'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $items    = $this->parseItems((string) ($settings['items'] ?? ''));
        if (! $items) return '';
        $expanded = $settings['expanded'] ?? 'first';

        $out = '<div style="margin:.65em 0">';
        foreach ($items as $i => [$title, $body]) {
            $open = match ($expanded) { 'all' => true, 'first' => $i === 0, default => false };
            $out .= '<details'.($open ? ' open' : '').' style="border:1px solid #e5e7eb;border-radius:.35rem;padding:.5rem .85rem;margin:.35em 0">'
                .'<summary style="cursor:pointer;font-weight:600">'.PageRenderer::renderText($title, $context, $decorate).'</summary>'
                .'<p style="margin:.5em 0 0;line-height:1.55">'.PageRenderer::renderText($body, $context, $decorate).'</p>'
                .'</details>';
        }
        return $out.'</div>';
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $items = $this->parseItems((string) ($settings['items'] ?? ''));
        if (! $items) return '';

        $rows = '';
        foreach ($items as [$title, $body]) {
            $rows .= '<tr><td style="padding:8px 0 4px;font-family:-apple-system,system-ui,sans-serif">'
                .'<strong style="font-size:15px;color:#0f172a">'.PageRenderer::renderText($title, $context, false).'</strong>'
                .'<p style="margin:4px 0 8px;color:#334155;font-size:14px;line-height:1.55">'.PageRenderer::renderText($body, $context, false).'</p>'
                .'</td></tr>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0;border-collapse:collapse">'
            .$rows
            .'</table>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $items = $this->parseItems((string) ($settings['items'] ?? ''));
        if (! $items) return '';
        $out = '';
        foreach ($items as [$title, $body]) {
            $out .= 'Q: '.PageRenderer::substitute($title, $context)."\n"
                  .'A: '.PageRenderer::substitute($body,  $context)."\n\n";
        }
        return $out;
    }

    /** @return array<int, array{0:string,1:string}> */
    protected function parseItems(string $raw): array
    {
        $lines = preg_split('/\r?\n/', trim($raw)) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if (! str_contains($line, '|')) continue;
            [$title, $body] = array_map('trim', explode('|', $line, 2));
            if ($title === '') continue;
            $out[] = [$title, $body];
        }
        return $out;
    }
}
