<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('duplicates a block at the next sibling index in the root list', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $a = BlockFactory::make('heading');
    $a['settings']['text'] = 'one';
    $b = BlockFactory::make('paragraph');
    $b['settings']['text'] = 'two';
    $pb->blocks = [$a, $b];

    $pb->duplicateBlock('0');

    expect($pb->blocks)->toHaveCount(3)
        ->and($pb->blocks[0]['settings']['text'])->toBe('one')
        ->and($pb->blocks[1]['type'])->toBe('heading')
        ->and($pb->blocks[1]['settings']['text'])->toBe('one')
        ->and($pb->blocks[2]['type'])->toBe('paragraph');
});

it('gives the duplicate a fresh id so wire:key collisions are avoided', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $a = BlockFactory::make('heading');
    $pb->blocks = [$a];

    $pb->duplicateBlock('0');

    expect($pb->blocks[1]['id'])->not->toBe($pb->blocks[0]['id'])
        ->and($pb->blocks[1]['id'])->not->toBe('');
});

it('duplicates blocks at nested paths inside slot children', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $heading['settings']['text'] = 'inside';
    $section = BlockFactory::make('section');
    $section['children']['body'][] = $heading;
    $pb->blocks = [$section];

    $pb->duplicateBlock('0/body/0');

    expect($pb->blocks[0]['children']['body'])->toHaveCount(2)
        ->and($pb->blocks[0]['children']['body'][0]['settings']['text'])->toBe('inside')
        ->and($pb->blocks[0]['children']['body'][1]['settings']['text'])->toBe('inside')
        ->and($pb->blocks[0]['children']['body'][1]['id'])->not->toBe($pb->blocks[0]['children']['body'][0]['id']);
});

it('recursively re-ids nested children when duplicating a layout block', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $section = BlockFactory::make('section');
    $section['children']['body'][] = $heading;
    $pb->blocks = [$section];

    $pb->duplicateBlock('0');

    $originalKidId   = $pb->blocks[0]['children']['body'][0]['id'];
    $duplicateKidId  = $pb->blocks[1]['children']['body'][0]['id'];
    expect($duplicateKidId)->not->toBe($originalKidId);
});

it('pushes a history snapshot so undo removes the duplicate', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $a = BlockFactory::make('heading');
    $pb->blocks = [$a];

    $pb->duplicateBlock('0');
    expect($pb->blocks)->toHaveCount(2);

    $pb->undo();
    expect($pb->blocks)->toHaveCount(1);
});

it('is a no-op for a path that does not resolve to a block', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];

    $pb->duplicateBlock('5');

    expect($pb->blocks)->toHaveCount(1);
});
