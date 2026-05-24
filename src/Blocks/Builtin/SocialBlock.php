<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Social / links bar · pipe-separated row of label+url pairs the author
 * supplies as `Name|url` lines. Renders inline so it works in email
 * clients without external image hosting.
 */
class SocialBlock extends BlockType
{
    public static function key(): string   { return 'social'; }
    public static function label(): string { return 'Social links'; }
    public static function icon(): string  { return '↗'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'links' => [
                'kind'    => 'textarea',
                'label'   => 'Links (one Name|URL per line)',
                'default' => "Twitter|https://twitter.com/\nLinkedIn|https://linkedin.com/\nWebsite|https://example.com",
            ],
            'align' => [
                'kind'    => 'select',
                'label'   => 'Align',
                'default' => 'left',
                'options' => ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $items = $this->parseLinks((string) ($settings['links'] ?? ''));
        if (! $items) return '';

        $align = in_array($settings['align'] ?? 'left', ['left','center','right'], true) ? $settings['align'] : 'left';

        $rendered = array_map(function ($pair) use ($context, $decorate) {
            $label = PageRenderer::renderText($pair[0], $context, $decorate);
            $href  = htmlspecialchars(PageRenderer::substitute($pair[1], $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<a href="'.$href.'" style="color:var(--accent,#2C66E8);text-decoration:none;margin:0 .5em;font-weight:500">'.$label.'</a>';
        }, $items);

        return '<div style="margin:.85em 0;text-align:'.$align.';font-size:.9em">'
            .implode('<span style="color:#cbd5e1">·</span>', $rendered)
            .'</div>';
    }

    public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
    {
        $items = $this->parseLinks((string) ($settings['links'] ?? ''));
        if (! $items) return '';

        $align = in_array($settings['align'] ?? 'left', ['left','center','right'], true) ? $settings['align'] : 'left';

        $cells = array_map(function ($pair) use ($context) {
            $label = PageRenderer::renderText($pair[0], $context, false);
            $href  = htmlspecialchars(PageRenderer::substitute($pair[1], $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<td style="padding:0 8px"><a href="'.$href.'" style="color:#2C66E8;text-decoration:none;font-family:-apple-system,system-ui,sans-serif">'.$label.'</a></td>';
        }, $items);

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="'.$align.'" style="margin:12px 0;border-collapse:collapse">'
            .'<tr>'.implode('<td style="color:#cbd5e1">·</td>', $cells).'</tr>'
            .'</table>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $items = $this->parseLinks((string) ($settings['links'] ?? ''));
        if (! $items) return '';
        $rendered = array_map(
            fn ($pair) => PageRenderer::substitute($pair[0], $context).': '.PageRenderer::substitute($pair[1], $context),
            $items,
        );
        return implode(' · ', $rendered)."\n\n";
    }

    /** @return array<int, array{0:string,1:string}> */
    protected function parseLinks(string $raw): array
    {
        $lines = preg_split('/\r?\n/', trim($raw)) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if (! str_contains($line, '|')) continue;
            [$name, $url] = array_map('trim', explode('|', $line, 2));
            if ($name === '' || $url === '') continue;
            $out[] = [$name, $url];
        }
        return $out;
    }
}
