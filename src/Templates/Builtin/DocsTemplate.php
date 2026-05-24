<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class DocsTemplate extends Template
{
    public static function name(): string  { return 'docs'; }
    public static function label(): string { return 'Docs article'; }

    public static function description(): string
    {
        return 'A /docs/{slug} page with a sidebar table of contents on the left and the article body on the right.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'docs.show',
            'method'        => 'GET',
            'path_template' => '/docs/{slug}',
            'description'   => 'Documentation article · slug variable',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal',  'literal_value' => 'docs'],
                ['position' => 1, 'kind' => 'variable', 'variable_name' => 'slug'],
            ],
        ];
    }

    public static function variables(): array
    {
        return [[
            'name' => 'slug', 'label' => 'Slug', 'type' => 'slug',
            'description' => 'URL slug for the docs article',
            'examples' => ['getting-started', 'installation', 'configuration'],
        ]];
    }

    public static function blocks(): array
    {
        $row = self::block('columns', ['ratio' => '1-2', 'gap' => 'lg']);
        $row['children'] = [
            'left'  => [
                self::block('heading', ['text' => 'Contents', 'level' => 'h3']),
                self::block('list', [
                    'items' => "Overview\nInstallation\nConfiguration\nUsage\nTroubleshooting",
                    'style' => 'number',
                ]),
            ],
            'right' => [
                self::block('heading',   ['text' => '{{ slug }}', 'level' => 'h1']),
                self::block('paragraph', ['text' => 'A short intro to the topic. Explain what the reader will get out of this article in one or two sentences.']),
                self::block('code', [
                    'code'     => "composer require vendor/package\nphp artisan vendor:publish",
                    'language' => 'bash',
                ]),
                self::block('divider'),
                self::block('paragraph', ['text' => 'Wrap up with next steps and pointers to related articles.']),
            ],
        ];

        return [$row];
    }
}
