<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class BlogPostTemplate extends Template
{
    public static function name(): string  { return 'blog-post'; }
    public static function label(): string { return 'Blog post'; }

    public static function description(): string
    {
        return 'A single-post route at /blog/{slug} with a heading, hero image, and body paragraph.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'blog.show',
            'method'        => 'GET',
            'path_template' => '/blog/{slug}',
            'description'   => 'Single blog post · slug variable',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal',  'literal_value' => 'blog'],
                ['position' => 1, 'kind' => 'variable', 'variable_name' => 'slug'],
            ],
        ];
    }

    public static function variables(): array
    {
        return [[
            'name' => 'slug', 'label' => 'Slug', 'type' => 'slug',
            'description' => 'URL slug for the blog post',
            'examples' => ['hello-world', 'getting-started', 'release-notes'],
        ]];
    }

    public static function blocks(): array
    {
        return [
            self::block('heading',   ['text' => '{{ slug }}', 'level' => 'h1']),
            self::block('image',     ['src' => 'https://placehold.co/1200x400', 'alt' => 'Hero image']),
            self::block('paragraph', ['text' => 'Body copy goes here. Drop a {{ slug }} chip to weave the URL parameter into the page.']),
        ];
    }
}
