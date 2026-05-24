<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class ParagraphBlock extends BlockType
{
    public static function key(): string   { return 'paragraph'; }
    public static function label(): string { return 'Paragraph'; }
    public static function icon(): string  { return '¶'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'text' => ['kind' => 'textarea', 'label' => 'Text', 'default' => 'Some descriptive paragraph copy goes here.'],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        return '<p style="margin:.65em 0;line-height:1.55">'
            .nl2br(PageRenderer::renderText((string) ($settings['text'] ?? ''), $context, $decorate))
            .'</p>';
    }
}
