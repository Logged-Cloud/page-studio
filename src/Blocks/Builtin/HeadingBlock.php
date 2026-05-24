<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class HeadingBlock extends BlockType
{
    public static function key(): string   { return 'heading'; }
    public static function label(): string { return 'Heading'; }
    public static function icon(): string  { return 'H'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'text'  => ['kind' => 'text',   'label' => 'Text',  'default' => 'Section heading'],
            'level' => ['kind' => 'select', 'label' => 'Level', 'default' => 'h2',
                'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4']],
            'align' => ['kind' => 'select', 'label' => 'Align', 'default' => 'left',
                'options' => ['left' => 'Left', 'center' => 'Centre', 'right' => 'Right']],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $tag = in_array($settings['level'] ?? 'h2', ['h1','h2','h3','h4'], true) ? $settings['level'] : 'h2';
        $align = in_array($settings['align'] ?? 'left', ['left','center','right'], true) ? $settings['align'] : 'left';
        $text = PageRenderer::renderText((string) ($settings['text'] ?? ''), $context, $decorate);
        return sprintf('<%1$s style="text-align:%3$s;margin:.5em 0">%2$s</%1$s>', $tag, $text, $align);
    }
}
