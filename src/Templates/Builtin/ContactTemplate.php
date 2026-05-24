<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class ContactTemplate extends Template
{
    public static function name(): string  { return 'contact'; }
    public static function label(): string { return 'Contact page'; }

    public static function description(): string
    {
        return 'A static /contact page with a heading, lead paragraph, and a panel listing email and phone.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'contact',
            'method'        => 'GET',
            'path_template' => '/contact',
            'description'   => 'Contact page',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal', 'literal_value' => 'contact'],
            ],
        ];
    }

    public static function blocks(): array
    {
        $panel = self::block('panel', ['border' => 'solid']);
        $panel['children'] = [
            'body' => [
                self::block('paragraph', ['text' => 'Email: hello@example.com']),
                self::block('paragraph', ['text' => 'Phone: +44 20 0000 0000']),
            ],
        ];

        $section = self::block('section', ['background' => 'tint', 'padding' => 'lg']);
        $section['children'] = [
            'body' => [
                self::block('heading',   ['text' => 'Get in touch', 'level' => 'h1']),
                self::block('paragraph', ['text' => 'Drop us a line and we will get back to you within one working day.']),
                $panel,
            ],
        ];

        return [$section];
    }
}
