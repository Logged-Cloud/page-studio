<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class LandingTemplate extends Template
{
    public static function name(): string  { return 'landing'; }
    public static function label(): string { return 'Marketing landing'; }

    public static function description(): string
    {
        return 'A static /welcome landing page with a hero, three-column feature row, and a CTA.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'welcome',
            'method'        => 'GET',
            'path_template' => '/welcome',
            'description'   => 'Marketing landing page',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal', 'literal_value' => 'welcome'],
            ],
        ];
    }

    public static function blocks(): array
    {
        $hero3 = self::block('columns-3', ['gap' => 'md']);
        $hero3['children'] = [
            'left'   => [self::block('heading', ['text' => 'Fast', 'level' => 'h3']), self::block('paragraph', ['text' => 'Built for speed.'])],
            'middle' => [self::block('heading', ['text' => 'Simple', 'level' => 'h3']), self::block('paragraph', ['text' => 'No surprises.'])],
            'right'  => [self::block('heading', ['text' => 'Honest', 'level' => 'h3']), self::block('paragraph', ['text' => 'Open by default.'])],
        ];

        return [
            self::block('heading',   ['text' => 'Welcome', 'level' => 'h1', 'align' => 'center']),
            self::block('paragraph', ['text' => 'A short pitch for the product, two sentences max.']),
            self::block('divider'),
            $hero3,
            self::block('divider'),
            self::block('button', ['label' => 'Get started', 'href' => '/sign-up', 'variant' => 'primary']),
        ];
    }
}
