<?php

use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('renders a columns block nested inside another columns block', function () {
    $html = PageRenderer::render([
        [
            'type'     => 'columns',
            'settings' => ['ratio' => '1-1'],
            'children' => [
                'left' => [
                    ['type' => 'paragraph', 'settings' => ['text' => 'OUTER-LEFT']],
                ],
                'right' => [
                    [
                        'type'     => 'columns',
                        'settings' => ['ratio' => '1-1'],
                        'children' => [
                            'left'  => [['type' => 'paragraph', 'settings' => ['text' => 'INNER-LEFT']]],
                            'right' => [['type' => 'paragraph', 'settings' => ['text' => 'INNER-RIGHT']]],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($html)
        ->toContain('OUTER-LEFT')
        ->toContain('INNER-LEFT')
        ->toContain('INNER-RIGHT');
});

it('renders columns-3 nested inside columns-3 (six leaves total)', function () {
    $leaf = fn (string $tag) => ['type' => 'paragraph', 'settings' => ['text' => $tag]];

    $innerThree = fn (string $prefix) => [
        'type'     => 'columns-3',
        'settings' => [],
        'children' => [
            'left'   => [$leaf($prefix.'-L')],
            'middle' => [$leaf($prefix.'-M')],
            'right'  => [$leaf($prefix.'-R')],
        ],
    ];

    $html = PageRenderer::render([
        [
            'type'     => 'columns-3',
            'settings' => [],
            'children' => [
                'left'   => [$innerThree('A')],
                'middle' => [$innerThree('B')],
                'right'  => [$innerThree('C')],
            ],
        ],
    ]);

    foreach (['A-L','A-M','A-R','B-L','B-M','B-R','C-L','C-M','C-R'] as $tag) {
        expect($html)->toContain($tag);
    }
});

it('renders a panel inside a column inside a panel inside a column · 4 levels deep', function () {
    $html = PageRenderer::render([
        [
            'type'     => 'columns',
            'settings' => ['ratio' => '1-1'],
            'children' => [
                'left'  => [['type' => 'paragraph', 'settings' => ['text' => 'L0']]],
                'right' => [[
                    'type'     => 'panel',
                    'settings' => [],
                    'children' => [
                        'body' => [[
                            'type'     => 'columns-3',
                            'settings' => [],
                            'children' => [
                                'left'   => [['type' => 'paragraph', 'settings' => ['text' => 'L1']]],
                                'middle' => [[
                                    'type'     => 'panel',
                                    'settings' => [],
                                    'children' => [
                                        'body' => [['type' => 'paragraph', 'settings' => ['text' => 'L4-deep']]],
                                    ],
                                ]],
                                'right'  => [['type' => 'paragraph', 'settings' => ['text' => 'L1-right']]],
                            ],
                        ]],
                    ],
                ]],
            ],
        ],
    ]);

    expect($html)
        ->toContain('L0')
        ->toContain('L1')
        ->toContain('L1-right')
        ->toContain('L4-deep');
});
