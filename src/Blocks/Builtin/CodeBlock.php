<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;

class CodeBlock extends BlockType
{
    public static function key(): string   { return 'code'; }
    public static function label(): string { return 'Code'; }
    public static function icon(): string  { return '⌘'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'code'     => ['kind' => 'textarea', 'label' => 'Code', 'default' => "console.log('hello')"],
            'language' => ['kind' => 'text',     'label' => 'Language', 'default' => 'js'],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        return sprintf(
            '<pre style="background:#0f172a;color:#e2e8f0;padding:.85rem 1.1rem;border-radius:.35rem;overflow-x:auto;font-family:ui-monospace,monospace;font-size:.85rem;margin:.65em 0"><code data-language="%s">%s</code></pre>',
            htmlspecialchars((string) ($settings['language'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars((string) ($settings['code'] ?? ''),     ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
    }
}
