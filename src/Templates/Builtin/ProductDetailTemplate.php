<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class ProductDetailTemplate extends Template
{
    public static function name(): string  { return 'product'; }
    public static function label(): string { return 'Product detail'; }

    public static function description(): string
    {
        return 'A /products/{slug} page with a hero image on the left and copy, price, and CTA on the right.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'products.show',
            'method'        => 'GET',
            'path_template' => '/products/{slug}',
            'description'   => 'Single product page · slug variable',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal',  'literal_value' => 'products'],
                ['position' => 1, 'kind' => 'variable', 'variable_name' => 'slug'],
            ],
        ];
    }

    public static function variables(): array
    {
        return [[
            'name' => 'slug', 'label' => 'Slug', 'type' => 'slug',
            'description' => 'URL slug for the product',
            'examples' => ['blue-mug', 'leather-wallet', 'travel-kettle'],
        ]];
    }

    public static function blocks(): array
    {
        $row = self::block('columns', ['ratio' => '1-1', 'gap' => 'lg']);
        $row['children'] = [
            'left'  => [
                self::block('image', ['src' => 'https://placehold.co/600x600', 'alt' => 'Product photo']),
            ],
            'right' => [
                self::block('paragraph', ['text' => 'A short, punchy description of what this product is and why it is worth picking up.']),
                self::block('paragraph', ['text' => 'Price: {{ price }}']),
                self::block('button', ['label' => 'Add to basket', 'href' => '#', 'variant' => 'primary']),
            ],
        ];

        return [
            self::block('heading', ['text' => '{{ slug }}', 'level' => 'h1']),
            $row,
        ];
    }
}
