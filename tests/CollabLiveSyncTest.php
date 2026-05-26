<?php

use Illuminate\Foundation\Auth\User;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Cross-tab live sync · the same human (or two distinct authors)
 * watching the editor on two devices/tabs. Edits in Tab A must
 * show up in Tab B within one heartbeat tick, without either tab
 * needing to manually save / reload.
 *
 * Two halves drive this:
 *
 *  1. Tab A · `wire:model.live` propagates settings input edits
 *     into the server-side $blocks property. An `updatedBlocks`
 *     hook persists that change to the DB so Tab B can read it.
 *
 *  2. Tab B · the existing 8s heartbeat calls a new
 *     `pullCollabUpdates($sinceIso)` method that returns the
 *     latest blocks+meta only when the page row's updated_at is
 *     fresher than $sinceIso. The client merges into local
 *     state.
 */

function syncAuthAs(int $id, string $name): User
{
    $u = new class extends User {
        protected $guarded = [];
        protected $table   = 'users';
    };
    $u->forceFill(['id' => $id, 'name' => $name]);
    $u->exists = true;
    auth()->setUser($u);
    return $u;
}

function syncMount(int $pageId): PageBuilder
{
    $pb = new PageBuilder();
    $pb->mount(pageId: $pageId);
    return $pb;
}

it('pullCollabUpdates returns nothing when the page has not changed since $sinceIso', function () {
    syncAuthAs(1, 'Alice');
    $page = Page::create([
        'blocks' => [['id' => 'b-1', 'type' => 'heading', 'settings' => ['text' => 'Hello']]],
        'status' => 'draft',
    ]);

    $pb = syncMount($page->id);

    // Pass a $since AFTER the current updated_at · nothing newer to send.
    $payload = $pb->pullCollabUpdates(now()->addMinute()->toIso8601String());
    expect($payload)->toBeNull();
});

it('pullCollabUpdates returns blocks + meta + updatedAt when a peer has written newer state', function () {
    syncAuthAs(1, 'Alice');
    $page = Page::create([
        'blocks' => [['id' => 'b-1', 'type' => 'heading', 'settings' => ['text' => 'Hello']]],
        'status' => 'draft',
    ]);

    // Tab B mounts at $t0 · captures "what I currently know".
    $pb = syncMount($page->id);
    $since = $page->updated_at->toIso8601String();

    // Tab A writes new state directly to the DB · simulates the
    // updatedBlocks hook persisting an in-flight settings edit.
    // Force updated_at forward so the freshness check sees the
    // peer's write as strictly newer than $since, no matter how
    // fast the test suite runs.
    $page->forceFill([
        'blocks'     => [['id' => 'b-1', 'type' => 'heading', 'settings' => ['text' => 'Hello {{ userId }}']]],
        'meta'       => ['title' => 'Updated by peer'],
        'updated_at' => now()->addSeconds(5),
    ])->save();

    $payload = $pb->pullCollabUpdates($since);

    expect($payload)->not->toBeNull()
        ->and($payload['blocks'][0]['settings']['text'])->toBe('Hello {{ userId }}')
        ->and($payload['meta']['title'])->toBe('Updated by peer')
        ->and(strtotime($payload['updatedAt']))->toBeGreaterThan(strtotime($since));
});

it('pullCollabUpdates is a no-op in ephemeral mode (no page binding)', function () {
    syncAuthAs(1, 'Alice');

    $pb = new PageBuilder();
    $pb->mount(); // ephemeral · no pageId / routeId

    expect($pb->pullCollabUpdates(null))->toBeNull()
        ->and($pb->pullCollabUpdates('2020-01-01T00:00:00Z'))->toBeNull();
});

it('updatedBlocks hook persists the new tree to the DB so peers can pull it via heartbeat', function () {
    syncAuthAs(1, 'Alice');
    $page = Page::create([
        'blocks' => [['id' => 'b-1', 'type' => 'heading', 'settings' => ['text' => 'old']]],
        'status' => 'draft',
    ]);

    $pb = syncMount($page->id);

    // Mimic wire:model.live debounce.300ms landing on the server ·
    // Livewire writes through to the public property and then
    // invokes the matching updatedFoo hook.
    $pb->blocks = [['id' => 'b-1', 'type' => 'heading', 'settings' => ['text' => 'new']]];
    $pb->updatedBlocks();

    $fresh = Page::find($page->id);
    expect($fresh->blocks[0]['settings']['text'])->toBe('new');
});

it('updatedMeta hook persists meta changes to the DB · meta inputs also use wire:model.live', function () {
    syncAuthAs(1, 'Alice');
    $page = Page::create([
        'blocks' => [],
        'meta'   => ['title' => 'old'],
        'status' => 'draft',
    ]);

    $pb = syncMount($page->id);

    $pb->meta = array_merge($pb->meta ?: [], ['title' => 'new']);
    $pb->updatedMeta();

    $fresh = Page::find($page->id);
    expect($fresh->meta['title'])->toBe('new');
});
