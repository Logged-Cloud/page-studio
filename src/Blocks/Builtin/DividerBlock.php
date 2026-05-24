<?php

namespace LoggedCloud\PageStudio\Blocks\Builtin;

use LoggedCloud\PageStudio\Blocks\BlockType;

class DividerBlock extends BlockType
{
    public static function key(): string   { return 'divider'; }
    public static function label(): string { return 'Divider'; }
    public static function icon(): string  { return '─'; }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        return '<hr style="border:none;border-top:1px solid #d0d5dd;margin:1em 0">';
    }
}
