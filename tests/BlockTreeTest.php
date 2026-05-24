<?php

use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Support\BlockTree;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

function tree(): array
{
    $columns = BlockFactory::make('columns');
    $columns['children']['left'][]  = BlockFactory::make('paragraph');
    $columns['children']['right'][] = BlockFactory::make('heading');
    return [
        BlockFactory::make('heading'),
        $columns,
    ];
}

test('insert appends to the root list when parentPath is empty', function () {
    $blocks = [];
    $blocks = BlockTree::insert($blocks, '', null, 0, BlockFactory::make('paragraph'));
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['type'])->toBe('paragraph');
});

test('insert into a slot pushes onto that slot only', function () {
    $blocks = tree();
    $blocks = BlockTree::insert($blocks, '1', 'left', 0, BlockFactory::make('quote'));
    expect($blocks[1]['children']['left'])->toHaveCount(2);
    expect($blocks[1]['children']['left'][0]['type'])->toBe('quote');
    expect($blocks[1]['children']['right'])->toHaveCount(1);
});

test('get walks down the path and returns the addressed block', function () {
    $blocks = tree();
    $kid = BlockTree::get($blocks, '1/right/0');
    expect($kid['type'])->toBe('heading');
});

test('remove drops the addressed block + reindexes its siblings', function () {
    $blocks = tree();
    $blocks = BlockTree::remove($blocks, '1/left/0');
    expect($blocks[1]['children']['left'])->toHaveCount(0);
});

test('remove at the root level reindexes correctly', function () {
    $blocks = tree();
    $blocks = BlockTree::remove($blocks, '0');
    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['type'])->toBe('columns');
});

test('move within the same slot reorders without duplication', function () {
    $blocks = tree();
    $blocks = BlockTree::insert($blocks, '1', 'left', 1, BlockFactory::make('quote'));
    // tree: columns.left = [paragraph, quote]
    $blocks = BlockTree::move($blocks, '1/left/0', '1', 'left', 2);
    // moving paragraph from index 0 to "end" (index 2 in pre-removal space)
    // should yield [quote, paragraph]
    expect($blocks[1]['children']['left'][0]['type'])->toBe('quote');
    expect($blocks[1]['children']['left'][1]['type'])->toBe('paragraph');
});

test('move can hop a block between containers', function () {
    $blocks = tree();
    $blocks = BlockTree::move($blocks, '1/left/0', '1', 'right', 0);
    expect($blocks[1]['children']['left'])->toHaveCount(0);
    expect($blocks[1]['children']['right'])->toHaveCount(2);
    expect($blocks[1]['children']['right'][0]['type'])->toBe('paragraph');
});

test('sanitise drops blocks of unknown types AND keeps nested ones', function () {
    $tree = tree();
    $tree[1]['children']['left'][] = ['id' => 'x', 'type' => 'phantom', 'settings' => []];
    $clean = BlockTree::sanitise($tree);
    expect($clean[1]['children']['left'])->toHaveCount(1);
    expect($clean[1]['children']['left'][0]['type'])->toBe('paragraph');
});

test('sanitise initialises missing slot arrays when a layout block lacks them', function () {
    $broken = [
        ['id' => 'a', 'type' => 'columns', 'settings' => ['ratio' => '1-1']],
    ];
    $clean = BlockTree::sanitise($broken);
    expect($clean[0]['children'])->toHaveKeys(['left', 'right']);
    expect($clean[0]['children']['left'])->toBe([]);
});
