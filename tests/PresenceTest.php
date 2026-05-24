<?php

use Illuminate\Foundation\Auth\User;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\Presence;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

function prAuthAs(int $id, string $name): User
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

function prPage(): Page
{
    return Page::create(['blocks' => [], 'status' => 'draft']);
}

it('heartbeatPresence creates a row on first call, then updates seen_at', function () {
    prAuthAs(1, 'Alice');
    $page = prPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    $pb->heartbeatPresence();
    expect(Presence::where('page_id', $page->id)->count())->toBe(1);

    $row = Presence::first();
    $original = $row->seen_at;

    // Wind seen_at back so the upsert observably moves it forward.
    $row->update(['seen_at' => now()->subSeconds(30)]);

    $pb->heartbeatPresence();
    expect(Presence::where('page_id', $page->id)->count())->toBe(1);

    $fresh = Presence::first();
    expect($fresh->seen_at->gt(now()->subSeconds(5)))->toBeTrue()
        ->and($fresh->id)->toBe($row->id);
});

it('activePeers excludes the current session', function () {
    prAuthAs(1, 'Alice');
    $page = prPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $pb->heartbeatPresence();

    // Inject a row pretending to be another tab.
    Presence::create([
        'page_id'     => $page->id,
        'author_id'   => 2,
        'author_name' => 'Bob',
        'session_id'  => 'other-tab',
        'seen_at'     => now(),
    ]);

    $peers = $pb->activePeers();
    expect($peers)->toHaveCount(1)
        ->and($peers[0]['name'])->toBe('Bob');
});

it('activePeers excludes rows older than the TTL', function () {
    prAuthAs(1, 'Alice');
    $page = prPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    // Fresh peer · should show up.
    Presence::create([
        'page_id'     => $page->id,
        'author_id'   => 2,
        'author_name' => 'Bob',
        'session_id'  => 'tab-bob',
        'seen_at'     => now(),
    ]);
    // Stale peer · last seen 5 minutes ago, should be filtered.
    Presence::create([
        'page_id'     => $page->id,
        'author_id'   => 3,
        'author_name' => 'Carol',
        'session_id'  => 'tab-carol',
        'seen_at'     => now()->subMinutes(5),
    ]);

    $peers = $pb->activePeers();
    $names = array_column($peers, 'name');
    expect($names)->toContain('Bob')
        ->and($names)->not->toContain('Carol');
});

it('ephemeral mode (no page binding) is a silent no-op', function () {
    prAuthAs(1, 'Alice');

    $pb = new PageBuilder();
    $pb->mount();

    $pb->heartbeatPresence();
    expect(Presence::count())->toBe(0)
        ->and($pb->activePeers())->toBe([]);
});
