<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\BlockLock;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    $this->page = Page::create(['blocks' => [], 'status' => 'draft']);
});

it('setEditingField stamps the field label onto the block lock row', function () {
    $pb = new PageBuilder();
    $pb->mount(pageId: $this->page->id);

    $pb->setEditingField('blk-1', 'Heading text');

    $row = BlockLock::where('page_id', $this->page->id)->where('block_id', 'blk-1')->first();
    expect($row)->not->toBeNull()
        ->and($row->field)->toBe('Heading text');
});

it('clearEditingField nulls the field but keeps the lock alive', function () {
    $pb = new PageBuilder();
    $pb->mount(pageId: $this->page->id);

    $pb->setEditingField('blk-1', 'Heading text');
    $pb->clearEditingField('blk-1');

    $row = BlockLock::where('page_id', $this->page->id)->where('block_id', 'blk-1')->first();
    expect($row->field)->toBeNull()
        ->and($row->expires_at->isFuture())->toBeTrue();
});

it('setEditingField refuses to stamp when another author holds the lock', function () {
    BlockLock::create([
        'page_id'     => $this->page->id,
        'block_id'    => 'blk-1',
        'author_id'   => 999,
        'author_name' => 'Alice',
        'expires_at'  => now()->addMinutes(5),
    ]);

    $pb = new PageBuilder();
    $pb->mount(pageId: $this->page->id);
    // pb's current author is anonymous (no auth()->user()) · different from Alice.
    $pb->setEditingField('blk-1', 'Heading text');

    $row = BlockLock::where('page_id', $this->page->id)->where('block_id', 'blk-1')->first();
    expect($row->author_name)->toBe('Alice')
        ->and($row->field)->toBeNull();
});

it('activeBlockLocks exposes the field so the ribbon can render it', function () {
    $pb = new PageBuilder();
    $pb->mount(pageId: $this->page->id);

    BlockLock::create([
        'page_id'     => $this->page->id,
        'block_id'    => 'blk-1',
        'author_id'   => 999,
        'author_name' => 'Alice',
        'field'       => 'Subheading',
        'expires_at'  => now()->addMinutes(5),
    ]);

    $locks = $pb->activeBlockLocks();
    expect($locks)->toHaveKey('blk-1')
        ->and($locks['blk-1']['name'])->toBe('Alice')
        ->and($locks['blk-1']['field'])->toBe('Subheading');
});

it('long field labels truncate to 64 chars', function () {
    $pb = new PageBuilder();
    $pb->mount(pageId: $this->page->id);

    $pb->setEditingField('blk-1', str_repeat('x', 120));

    $row = BlockLock::where('page_id', $this->page->id)->where('block_id', 'blk-1')->first();
    expect(mb_strlen($row->field))->toBe(64);
});
