<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Snippet;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('saveAsSnippet persists a Snippet row with the source block tree', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $heading['settings']['text'] = 'Hello hero';
    $pb->blocks = [$heading];

    $pb->saveAsSnippet('0', 'hero', 'Hero callout');

    $row = Snippet::where('name', 'hero')->first();
    expect($row)->not->toBeNull()
        ->and($row->label)->toBe('Hero callout')
        ->and($row->group)->toBe('snippets')
        ->and(($row->block)['type'] ?? null)->toBe('heading')
        ->and(($row->block)['settings']['text'] ?? null)->toBe('Hello hero');
});

it('saveAsSnippet deep-clones with fresh ids so the saved block id differs from the source', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $pb->blocks = [$heading];
    $sourceId = $pb->blocks[0]['id'];

    $pb->saveAsSnippet('0', 'h1');

    $row = Snippet::where('name', 'h1')->first();
    expect(($row->block)['id'] ?? null)->not->toBe($sourceId);
});

it('saveAsSnippet on a missing path is a no-op', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];

    $pb->saveAsSnippet('9', 'ghost');

    expect(Snippet::count())->toBe(0);
});

it('saveAsSnippet refuses an empty name', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];

    $pb->saveAsSnippet('0', '   ');

    expect(Snippet::count())->toBe(0);
});

it('dropSnippet inserts a copy into the block tree at the target', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $heading['settings']['text'] = 'Saved';
    $pb->blocks = [$heading];
    $pb->saveAsSnippet('0', 'hero');

    // Fresh builder · empty tree · drop the snippet onto root.
    $pb2 = new PageBuilder();
    $pb2->mount();
    $pb2->dropSnippet('hero');

    expect($pb2->blocks)->toHaveCount(1)
        ->and($pb2->blocks[0]['type'])->toBe('heading')
        ->and($pb2->blocks[0]['settings']['text'])->toBe('Saved');
});

it('dropSnippet honours the (parentPath, slot, index) target shape', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $heading = BlockFactory::make('heading');
    $heading['settings']['text'] = 'inside';
    $pb->blocks = [$heading];
    $pb->saveAsSnippet('0', 'inner');

    $pb2 = new PageBuilder();
    $pb2->mount();
    $section = BlockFactory::make('section');
    $pb2->blocks = [$section];

    $pb2->dropSnippet('inner', '0', 'body', 0);

    expect($pb2->blocks[0]['children']['body'])->toHaveCount(1)
        ->and($pb2->blocks[0]['children']['body'][0]['type'])->toBe('heading')
        ->and($pb2->blocks[0]['children']['body'][0]['settings']['text'])->toBe('inside');
});

it('dropSnippet pushes a history snapshot so undo restores the prior tree', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];
    $pb->saveAsSnippet('0', 'h');

    $pb2 = new PageBuilder();
    $pb2->mount();
    $pb2->blocks = [];
    $pb2->dropSnippet('h');
    expect($pb2->blocks)->toHaveCount(1);

    $pb2->undo();
    expect($pb2->blocks)->toHaveCount(0);
});

it('dropSnippet deep-clones at drop time so two drops produce distinct ids', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];
    $pb->saveAsSnippet('0', 'h');

    $pb2 = new PageBuilder();
    $pb2->mount();
    $pb2->dropSnippet('h');
    $pb2->dropSnippet('h');

    expect($pb2->blocks)->toHaveCount(2)
        ->and($pb2->blocks[0]['id'])->not->toBe($pb2->blocks[1]['id']);
});

it('dropSnippet on a missing name is a no-op', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [];

    $pb->dropSnippet('does-not-exist');

    expect($pb->blocks)->toBe([]);
});

it('snippetLibrary computed returns the saved snippets without the block payload', function () {
    Snippet::create([
        'name'  => 'one',
        'label' => 'One',
        'icon'  => '★',
        'group' => 'snippets',
        'block' => ['id' => 'x', 'type' => 'heading', 'settings' => []],
    ]);
    Snippet::create([
        'name'  => 'two',
        'label' => 'Two',
        'icon'  => '◻︎',
        'group' => 'headers',
        'block' => ['id' => 'y', 'type' => 'heading', 'settings' => []],
    ]);

    $pb = new PageBuilder();
    $pb->mount();
    $entries = $pb->snippetLibrary();

    expect($entries)->toHaveCount(2);
    foreach ($entries as $row) {
        expect($row)->toHaveKeys(['id', 'name', 'label', 'icon', 'group'])
            ->and($row)->not->toHaveKey('block');
    }
});

it('renameSnippet updates name and label', function () {
    $row = Snippet::create([
        'name'  => 'old-name',
        'label' => 'Old label',
        'icon'  => '★',
        'group' => 'snippets',
        'block' => ['id' => 'x', 'type' => 'heading', 'settings' => []],
    ]);

    $pb = new PageBuilder();
    $pb->mount();
    $pb->renameSnippet($row->id, 'new-name', 'New label');

    $fresh = Snippet::find($row->id);
    expect($fresh->name)->toBe('new-name')
        ->and($fresh->label)->toBe('New label');
});

it('deleteSnippet removes the row', function () {
    $row = Snippet::create([
        'name'  => 'goner',
        'label' => 'Goner',
        'icon'  => '★',
        'group' => 'snippets',
        'block' => ['id' => 'x', 'type' => 'heading', 'settings' => []],
    ]);

    $pb = new PageBuilder();
    $pb->mount();
    $pb->deleteSnippet($row->id);

    expect(Snippet::find($row->id))->toBeNull();
});

it('round-trips a hero block through save + drop into a separate builder mount', function () {
    $pb = new PageBuilder();
    $pb->mount();
    $hero = BlockFactory::make('section');
    $inner = BlockFactory::make('heading');
    $inner['settings']['text'] = 'Big idea';
    $hero['children']['body'][] = $inner;
    $pb->blocks = [$hero];

    $pb->saveAsSnippet('0', 'hero');

    $pb2 = new PageBuilder();
    $pb2->mount();
    $pb2->dropSnippet('hero');

    expect($pb2->blocks)->toHaveCount(1)
        ->and($pb2->blocks[0]['type'])->toBe('section')
        ->and($pb2->blocks[0]['children']['body'][0]['type'])->toBe('heading')
        ->and($pb2->blocks[0]['children']['body'][0]['settings']['text'])->toBe('Big idea')
        // Fresh ids so the dropped subtree never collides with the source.
        ->and($pb2->blocks[0]['id'])->not->toBe($pb->blocks[0]['id'])
        ->and($pb2->blocks[0]['children']['body'][0]['id'])->not->toBe($pb->blocks[0]['children']['body'][0]['id']);
});
