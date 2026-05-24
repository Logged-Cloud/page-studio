<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Embed · responsive iframe wrapper for third-party video players (YouTube,
 * Vimeo, Loom). Detects the provider from the URL and rewrites to its
 * embed-friendly form. Falls back to using the URL as-is for any other host.
 */
class EmbedBlock extends BlockType
{
    public static function key(): string   { return 'embed'; }
    public static function label(): string { return 'Embed'; }
    public static function icon(): string  { return '▶'; }
    public static function group(): string { return 'content'; }
    // Iframes are silently stripped by every major email client.
    public static function emailSafe(): bool { return false; }

    public static function settings(): array
    {
        return [
            'url'    => ['kind' => 'url', 'label' => 'URL', 'default' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'aspect' => [
                'kind'    => 'select',
                'label'   => 'Aspect',
                'default' => '16-9',
                'options' => ['16-9' => '16:9', '4-3' => '4:3', '1-1' => '1:1'],
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $url    = htmlspecialchars(self::toEmbedUrl(PageRenderer::substitute((string) ($settings['url'] ?? ''), $context, false)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $aspect = $settings['aspect'] ?? '16-9';
        $pad    = match ($aspect) { '4-3' => '75%', '1-1' => '100%', default => '56.25%' };

        return '<div style="position:relative;padding-bottom:'.$pad.';height:0;overflow:hidden;margin:.65em 0">'
            .'<iframe src="'.$url.'" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0" allowfullscreen></iframe>'
            .'</div>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $url = PageRenderer::substitute((string) ($settings['url'] ?? ''), $context);
        return "[video] {$url}\n\n";
    }

    /** Rewrite the share URL to its iframe-friendly embed form. */
    protected static function toEmbedUrl(string $url): string
    {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }
        if (preg_match('#loom\.com/share/([\w-]+)#', $url, $m)) {
            return 'https://www.loom.com/embed/'.$m[1];
        }
        return $url;
    }
}
