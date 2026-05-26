<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('mutating a duplicated node does not bleed into the original', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->nodes = [
        ['id' => 'src', 'type' => 'output', 'position' => ['x' => 100, 'y' => 100],
         'settings' => ['name' => 'newVar1']],
    ];
    $pb->edges = [];

    $pb->duplicateNode('src');

    expect($pb->nodes)->toHaveCount(2)
        ->and($pb->nodes[0]['id'])->toBe('src')
        ->and($pb->nodes[1]['id'])->not->toBe('src');

    $cloneIndex = 1;
    $cloneId    = $pb->nodes[$cloneIndex]['id'];

    // Mutate the clone's settings · the original must stay untouched.
    $pb->nodes[$cloneIndex]['settings']['name'] = 'cloneOnly';

    expect($pb->nodes[0]['settings']['name'])->toBe('newVar1', 'original should NOT receive the clone-side mutation')
        ->and($pb->nodes[$cloneIndex]['settings']['name'])->toBe('cloneOnly');
});

it('mutating the original after a duplicate does not bleed into the clone', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->nodes = [
        ['id' => 'src', 'type' => 'output', 'position' => ['x' => 0, 'y' => 0],
         'settings' => ['name' => 'shared']],
    ];

    $pb->duplicateNode('src');

    $pb->nodes[0]['settings']['name'] = 'changedOriginal';

    expect($pb->nodes[1]['settings']['name'])->toBe('shared', 'clone should NOT receive original-side mutation');
});

it('duplicating twice produces three independent settings arrays', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->nodes = [
        ['id' => 'src', 'type' => 'output', 'position' => ['x' => 0, 'y' => 0],
         'settings' => ['name' => 'a']],
    ];

    $pb->duplicateNode('src');
    $pb->duplicateNode('src');

    expect($pb->nodes)->toHaveCount(3);
    $pb->nodes[0]['settings']['name'] = 'A';
    $pb->nodes[1]['settings']['name'] = 'B';
    $pb->nodes[2]['settings']['name'] = 'C';

    expect($pb->nodes[0]['settings']['name'])->toBe('A')
        ->and($pb->nodes[1]['settings']['name'])->toBe('B')
        ->and($pb->nodes[2]['settings']['name'])->toBe('C');
});

it('mutating a duplicated block does not bleed into the original', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [
        ['id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'Original', 'level' => 'h1', 'align' => 'left']],
    ];

    $pb->duplicateBlock('0');

    expect($pb->blocks)->toHaveCount(2);

    $pb->blocks[1]['settings']['text'] = 'Clone text';

    expect($pb->blocks[0]['settings']['text'])->toBe('Original',
        'duplicateBlock should produce an independent copy · the original must not echo the clone-side edit');
});
