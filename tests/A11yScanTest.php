<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('runA11yScan flags an image block missing its alt text', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'i1', 'type' => 'image', 'settings' => ['src' => '/hero.png', 'alt' => '']],
    ];

    $pb->runA11yScan();

    expect($pb->a11yFindings)
        ->toHaveCount(1)
        ->and($pb->a11yFindings[0]['kind'])->toBe('Missing alt text');
});

it('runA11yScan ignores image blocks that DO have alt text', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'i1', 'type' => 'image', 'settings' => ['src' => '/hero.png', 'alt' => 'A red hero image']],
    ];

    $pb->runA11yScan();

    expect($pb->a11yFindings)->toBe([]);
});

it('runA11yScan flags a heading-level skip (h1 -> h3)', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'Title', 'level' => 'h1']],
        ['id' => 'h3', 'type' => 'heading', 'settings' => ['text' => 'Subsubtitle', 'level' => 'h3']],
    ];

    $pb->runA11yScan();

    expect($pb->a11yFindings)
        ->toHaveCount(1)
        ->and($pb->a11yFindings[0]['kind'])->toBe('Heading-level skip');
});

it('runA11yScan walks into layout children', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'p1', 'type' => 'panel', 'settings' => ['title' => 'Body'], 'children' => [
            'body' => [
                ['id' => 'i2', 'type' => 'image', 'settings' => ['src' => '/x.png']],
            ],
        ]],
    ];

    $pb->runA11yScan();

    expect($pb->a11yFindings)
        ->toHaveCount(1)
        ->and($pb->a11yFindings[0]['kind'])->toBe('Missing alt text');
});

it('moveSelectedBlock dispatches to moveSibling for the current selection', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'a', 'type' => 'heading',   'settings' => ['text' => 'A', 'level' => 'h1']],
        ['id' => 'b', 'type' => 'paragraph', 'settings' => ['text' => 'B']],
        ['id' => 'c', 'type' => 'button',    'settings' => ['label' => 'C']],
    ];
    $pb->selectedPath = '1'; // block "b" at the root

    $pb->moveSelectedBlock(-1);

    expect($pb->blocks[0]['id'])->toBe('b')
        ->and($pb->blocks[1]['id'])->toBe('a');
});

it('moveSelectedBlock is a no-op when nothing is selected', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'A', 'level' => 'h1']],
        ['id' => 'b', 'type' => 'paragraph', 'settings' => ['text' => 'B']],
    ];
    // selectedPath stays ''
    $pb->moveSelectedBlock(1);

    expect($pb->blocks[0]['id'])->toBe('a');
});
