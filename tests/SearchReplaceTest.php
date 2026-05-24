<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('replaces a plain string across a flat block list', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $a = BlockFactory::make('heading');
    $a['settings']['text'] = 'Hello world';
    $b = BlockFactory::make('paragraph');
    $b['settings']['text'] = 'world domination';
    $pb->blocks = [$a, $b];

    $count = $pb->searchAndReplace('world', 'earth', false);

    expect($count)->toBe(2)
        ->and($pb->blocks[0]['settings']['text'])->toBe('Hello earth')
        ->and($pb->blocks[1]['settings']['text'])->toBe('earth domination');
});

it('descends into nested slot children when replacing', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $heading = BlockFactory::make('heading');
    $heading['settings']['text'] = 'nested world';
    $section = BlockFactory::make('section');
    $section['children']['body'][] = $heading;
    $pb->blocks = [$section];

    $count = $pb->searchAndReplace('world', 'planet', false);

    expect($count)->toBe(1)
        ->and($pb->blocks[0]['children']['body'][0]['settings']['text'])->toBe('nested planet');
});

it('honours capture groups in regex mode', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $h = BlockFactory::make('heading');
    $h['settings']['text'] = 'price 42 dollars';
    $pb->blocks = [$h];

    $count = $pb->searchAndReplace('/price (\d+)/', 'cost $1', true);

    expect($count)->toBe(1)
        ->and($pb->blocks[0]['settings']['text'])->toBe('cost 42 dollars');
});

it('returns 0 and leaves the tree untouched on a bad regex', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $h = BlockFactory::make('heading');
    $h['settings']['text'] = 'untouched';
    $pb->blocks = [$h];
    $before = $pb->blocks;

    $count = $pb->searchAndReplace('/[unclosed', 'x', true);

    expect($count)->toBe(0)
        ->and($pb->blocks)->toBe($before);
});

it('returns the count of changed blocks not the count of matches', function () {
    $pb = new PageBuilder();
    $pb->mount();

    // Three blocks, two contain "foo" (one has it twice in different
    // settings — still counts as a single changed block).
    $a = BlockFactory::make('heading');
    $a['settings']['text'] = 'foo bar foo';
    $b = BlockFactory::make('paragraph');
    $b['settings']['text'] = 'foo here';
    $c = BlockFactory::make('paragraph');
    $c['settings']['text'] = 'nothing';
    $pb->blocks = [$a, $b, $c];

    $count = $pb->searchAndReplace('foo', 'baz', false);

    expect($count)->toBe(2);
});

it('pushes a history snapshot so undo restores the pre-replace state', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $h = BlockFactory::make('heading');
    $h['settings']['text'] = 'original';
    $pb->blocks = [$h];

    $pb->searchAndReplace('original', 'changed', false);
    expect($pb->blocks[0]['settings']['text'])->toBe('changed');

    $pb->undo();
    expect($pb->blocks[0]['settings']['text'])->toBe('original');
});

it('does not burn an undo step when the find string matches nothing', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $h = BlockFactory::make('heading');
    $h['settings']['text'] = 'unchanged';
    $pb->blocks = [$h];

    $undoBefore = count($pb->undoStack);
    $count = $pb->searchAndReplace('missing-term', 'x', false);

    expect($count)->toBe(0)
        ->and(count($pb->undoStack))->toBe($undoBefore);
});

it('exposes searchAndReplace as a Livewire-callable action', function () {
    $h = BlockFactory::make('heading');
    $h['settings']['text'] = 'Hello world';

    $tc = \Livewire\Livewire::test(PageBuilder::class)
        ->set('blocks', [$h])
        ->call('searchAndReplace', 'world', 'planet', false);

    $tc->assertSet('blocks.0.settings.text', 'Hello planet');
});

it('ignores non-string settings values like numbers and booleans', function () {
    $pb = new PageBuilder();
    $pb->mount();

    $h = BlockFactory::make('heading');
    $h['settings']['text']    = 'replace me';
    $h['settings']['flag']    = true;
    $h['settings']['count']   = 42;
    $h['settings']['list']    = ['replace me'];
    $pb->blocks = [$h];

    $pb->searchAndReplace('replace me', 'X', false);

    expect($pb->blocks[0]['settings']['text'])->toBe('X')
        ->and($pb->blocks[0]['settings']['flag'])->toBeTrue()
        ->and($pb->blocks[0]['settings']['count'])->toBe(42)
        ->and($pb->blocks[0]['settings']['list'])->toBe(['replace me']);
});
