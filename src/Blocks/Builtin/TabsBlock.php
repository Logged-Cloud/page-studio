<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Tabs · three-slot layout block with an Alpine-driven nav. Email render
 * flattens to stacked sections (label as heading + slot body) since the
 * JS interactivity doesn't survive in email clients.
 */
class TabsBlock extends BlockType
{
    public static function key(): string   { return 'tabs'; }
    public static function label(): string { return 'Tabs'; }
    public static function icon(): string  { return '⊟'; }
    public static function group(): string { return 'layout'; }
    public static function emailSafe(): bool { return true; }

    public static function slots(): array
    {
        return [
            'tab1' => ['label' => 'Tab 1'],
            'tab2' => ['label' => 'Tab 2'],
            'tab3' => ['label' => 'Tab 3'],
        ];
    }

    public static function settings(): array
    {
        return [
            'labels' => [
                'kind'    => 'textarea',
                'label'   => 'Tab labels (one per line)',
                'default' => "Overview\nDetails\nReviews",
            ],
            'active' => ['kind' => 'number', 'label' => 'Active tab (0-2)', 'default' => 0],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $labels = $this->parseLabels((string) ($settings['labels'] ?? ''));
        $active = max(0, min(2, (int) ($settings['active'] ?? 0)));

        $nav = '';
        foreach ($labels as $i => $label) {
            // Active-button styling is computed inline via x-bind so the
            // initial paint matches what Alpine reactivity produces.
            $nav .= '<button type="button" @click="active = '.$i.'" '
                .':style="active === '.$i.' ? \'background:#2C66E8;color:#fff\' : \'background:transparent;color:#475569\'" '
                .'style="border:1px solid #cbd5e1;padding:.4rem .9rem;margin-right:.3rem;border-radius:.35rem;cursor:pointer;font:inherit">'
                .PageRenderer::renderText($label, $context, $decorate)
                .'</button>';
        }

        $slots = ['tab1', 'tab2', 'tab3'];
        $bodies = '';
        foreach ($slots as $i => $slot) {
            $bodies .= '<div x-show="active === '.$i.'" style="padding:.85rem 0">'
                .PageRenderer::renderChildren($children, $slot, $context, $decorate)
                .'</div>';
        }

        return '<div x-data="{ active: '.$active.' }" style="margin:.65em 0">'
            .'<div style="margin-bottom:.5em">'.$nav.'</div>'
            .$bodies
            .'</div>';
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $labels = $this->parseLabels((string) ($settings['labels'] ?? ''));
        $slots  = ['tab1', 'tab2', 'tab3'];

        $out = '';
        foreach ($slots as $i => $slot) {
            $label = PageRenderer::renderText($labels[$i] ?? '', $context, false);
            $body  = PageRenderer::renderChildrenForEmail($children, $slot, $context, $decorate);
            $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:12px 0;border-collapse:collapse">'
                .'<tr><td style="font-family:-apple-system,system-ui,sans-serif">'
                .($label !== '' ? '<h3 style="margin:0 0 8px;font-size:16px;color:#0f172a">'.$label.'</h3>' : '')
                .$body
                .'</td></tr>'
                .'</table>';
        }
        return $out;
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $labels = $this->parseLabels((string) ($settings['labels'] ?? ''));
        $slots  = ['tab1', 'tab2', 'tab3'];

        $out = '';
        foreach ($slots as $i => $slot) {
            $label = PageRenderer::substitute($labels[$i] ?? '', $context);
            $body  = PageRenderer::renderChildrenForText($children, $slot, $context);
            if ($label !== '') $out .= "# {$label}\n\n";
            $out .= $body;
        }
        return $out;
    }

    /** @return array<int, string> */
    protected function parseLabels(string $raw): array
    {
        $lines = preg_split('/\r?\n/', trim($raw)) ?: [];
        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }
}
