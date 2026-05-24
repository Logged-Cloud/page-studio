<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

/**
 * Self-hosted video · renders a native <video> tag for an mp4 / webm URL.
 * Distinct from EmbedBlock which wraps third-party iframe players.
 */
class VideoBlock extends BlockType
{
    public static function key(): string   { return 'video'; }
    public static function label(): string { return 'Video'; }
    public static function icon(): string  { return '🎬'; }
    public static function group(): string { return 'content'; }
    // <video> falls back to a broken-icon placeholder in most email clients.
    public static function emailSafe(): bool { return false; }

    public static function settings(): array
    {
        return [
            'src'      => ['kind' => 'url',  'label' => 'Source URL', 'default' => ''],
            'poster'   => ['kind' => 'url',  'label' => 'Poster URL', 'default' => ''],
            'autoplay' => ['kind' => 'bool', 'label' => 'Autoplay',   'default' => false],
            'loop'     => ['kind' => 'bool', 'label' => 'Loop',       'default' => false],
            'controls' => ['kind' => 'bool', 'label' => 'Controls',   'default' => true],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $src      = htmlspecialchars(PageRenderer::substitute((string) ($settings['src']    ?? ''), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $poster   = htmlspecialchars(PageRenderer::substitute((string) ($settings['poster'] ?? ''), $context, false), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $autoplay = ! empty($settings['autoplay']);
        $loop     = ! empty($settings['loop']);
        $controls = ! array_key_exists('controls', $settings) || ! empty($settings['controls']);

        $attrs = 'src="'.$src.'"';
        if ($poster !== '') $attrs .= ' poster="'.$poster.'"';
        if ($controls)      $attrs .= ' controls';
        if ($autoplay)      $attrs .= ' autoplay muted'; // browsers block autoplay without muted
        if ($loop)          $attrs .= ' loop';

        return '<video '.$attrs.' style="max-width:100%;height:auto;display:block;margin:.65em 0"></video>';
    }

    public function renderText(array $settings, array $children, array $context): ?string
    {
        $src = PageRenderer::substitute((string) ($settings['src'] ?? ''), $context);
        return "[video] {$src}\n\n";
    }
}
