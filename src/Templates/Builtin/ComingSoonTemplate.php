<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class ComingSoonTemplate extends Template
{
    public static function name(): string  { return 'coming-soon'; }
    public static function label(): string { return 'Coming soon'; }

    public static function description(): string
    {
        return 'A static /coming-soon holding page with a centred headline, lead paragraph, and a notify-me CTA.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'coming-soon',
            'method'        => 'GET',
            'path_template' => '/coming-soon',
            'description'   => 'Holding page · launching soon',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal', 'literal_value' => 'coming-soon'],
            ],
        ];
    }

    public static function blocks(): array
    {
        return [
            self::block('spacer',    ['size' => 'lg']),
            self::block('heading',   ['text' => 'Launching soon', 'level' => 'h1', 'align' => 'center']),
            self::block('paragraph', ['text' => "We're putting the finishing touches on it."]),
            self::block('divider'),
            self::block('button',    ['label' => 'Notify me', 'href' => '#', 'variant' => 'primary']),
        ];
    }
}
