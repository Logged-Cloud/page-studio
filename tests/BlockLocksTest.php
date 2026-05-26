<?php

use Illuminate\Foundation\Auth\User;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\BlockLock;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Spin up two distinct logged-in users so we can model "another author
 * already holds this lock" scenarios without needing a real Auth driver.
 * The helper returns a fresh User model with an id set explicitly.
 */
function blAuthAs(int $id, string $name): User
{
    $u = new class extends User {
        protected $guarded = [];
        protected $table   = 'users';
    };
    $u->forceFill(['id' => $id, 'name' => $name]);
    // Mark the model as existing so getKey() returns the id.
    $u->exists = true;
    auth()->setUser($u);
    return $u;
}

function blPage(): Page
{
    return Page::create(['blocks' => [], 'status' => 'draft']);
}

it('acquireBlockLock creates a row with expires_at in the future', function () {
    blAuthAs(1, 'Alice');
    $page = blPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    expect($pb->acquireBlockLock('blk-1'))->toBeTrue();

    $row = BlockLock::where('page_id', $page->id)->where('block_id', 'blk-1')->first();
    expect($row)->not->toBeNull()
        ->and($row->author_id)->toBe(1)
        ->and($row->author_name)->toBe('Alice')
        ->and($row->expires_at->isFuture())->toBeTrue();
});

it('returns false when another user already holds an active lock', function () {
    $page = blPage();

    // Alice claims the block.
    blAuthAs(1, 'Alice');
    $pbA = new PageBuilder();
    $pbA->mount(pageId: $page->id);
    expect($pbA->acquireBlockLock('blk-1'))->toBeTrue();

    // Bob tries the same block · refused.
    blAuthAs(2, 'Bob');
    $pbB = new PageBuilder();
    $pbB->mount(pageId: $page->id);
    expect($pbB->acquireBlockLock('blk-1'))->toBeFalse();
});

it('same user acquiring twice refreshes the row instead of inserting', function () {
    blAuthAs(1, 'Alice');
    $page = blPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    expect($pb->acquireBlockLock('blk-1'))->toBeTrue();
    $first  = BlockLock::where('block_id', 'blk-1')->first();
    $firstExpiry = $first->expires_at;

    // Wind expiry back so the refresh moves it forward observably.
    $first->update(['expires_at' => now()->addSeconds(1)]);

    expect($pb->acquireBlockLock('blk-1'))->toBeTrue();
    expect(BlockLock::where('block_id', 'blk-1')->count())->toBe(1);

    $fresh = BlockLock::where('block_id', 'blk-1')->first();
    expect($fresh->expires_at->gt(now()->addSeconds(5)))->toBeTrue();
});

it('heartbeatBlockLocks pushes expires_at forward on held locks', function () {
    blAuthAs(1, 'Alice');
    $page = blPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $pb->acquireBlockLock('blk-1');

    // Wind the expiry back to "almost stale".
    BlockLock::where('block_id', 'blk-1')->update(['expires_at' => now()->addSeconds(1)]);

    $pb->heartbeatBlockLocks(['blk-1']);

    $row = BlockLock::where('block_id', 'blk-1')->first();
    expect($row->expires_at->gt(now()->addSeconds(10)))->toBeTrue();
});

it('releaseBlockLock only removes the current user\'s lock', function () {
    $page = blPage();

    blAuthAs(1, 'Alice');
    $pbA = new PageBuilder();
    $pbA->mount(pageId: $page->id);
    $pbA->acquireBlockLock('blk-1');

    // Bob tries to release Alice's lock · should be a no-op.
    blAuthAs(2, 'Bob');
    $pbB = new PageBuilder();
    $pbB->mount(pageId: $page->id);
    $pbB->releaseBlockLock('blk-1');

    expect(BlockLock::where('block_id', 'blk-1')->count())->toBe(1);

    // Alice releases · gone.
    blAuthAs(1, 'Alice');
    $pbA->releaseBlockLock('blk-1');
    expect(BlockLock::where('block_id', 'blk-1')->count())->toBe(0);
});

it('activeBlockLocks computed returns locks held only by other users', function () {
    $page = blPage();

    // Alice claims blk-1.
    blAuthAs(1, 'Alice');
    $pbA = new PageBuilder();
    $pbA->mount(pageId: $page->id);
    $pbA->acquireBlockLock('blk-1');

    // Bob claims blk-2 and reads the computed · should see Alice's lock
    // on blk-1 but NOT his own on blk-2.
    blAuthAs(2, 'Bob');
    $pbB = new PageBuilder();
    $pbB->mount(pageId: $page->id);
    $pbB->acquireBlockLock('blk-2');

    $locks = $pbB->activeBlockLocks();
    expect($locks)->toHaveKey('blk-1')
        ->and($locks['blk-1']['name'])->toBe('Alice')
        ->and($locks)->not->toHaveKey('blk-2');
});

it('activeBlockLocks does NOT show the viewer\'s own lock back to them when they are anonymous · "I just clicked it, it\'s me"', function () {
    // Public-form / non-auth scenario · the single anonymous author
    // claims a block and then re-reads activeBlockLocks on the next
    // render. Their own claim must not appear as "Anonymous editing".
    $page = blPage();
    auth()->forgetGuards();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    expect($pb->acquireBlockLock('blk-1'))->toBeTrue();

    $row = BlockLock::where('block_id', 'blk-1')->first();
    expect($row)->not->toBeNull()
        ->and($row->author_id)->toBeNull()
        ->and($row->author_name)->toBe('Anonymous');

    expect($pb->activeBlockLocks())->not->toHaveKey('blk-1');
});

it('the viewer\'s own held locks surface via myBlockLocks so the template can render a "You editing" ribbon · NOT a "someone else" lock-out', function () {
    // Same scenario, but assert the positive-confirmation path · the
    // component should expose the blocks the current viewer holds so
    // the editor can render a green "You editing" ribbon and avoid
    // graying the block out.
    $page = blPage();
    auth()->forgetGuards();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    expect($pb->myBlockLocks())->toBe([]);

    $pb->acquireBlockLock('blk-self');

    expect($pb->myBlockLocks())->toHaveKey('blk-self')
        ->and($pb->activeBlockLocks())->not->toHaveKey('blk-self');

    // Releasing must drop it from the own-locks computed too.
    $pb->releaseBlockLock('blk-self');
    expect($pb->myBlockLocks())->not->toHaveKey('blk-self');
});

it('a second anonymous tab sees the first tab\'s lock in activeBlockLocks · own-lock filtering is component-scoped, not global', function () {
    // Without a session/component identity, we cannot truly tell two
    // anonymous tabs apart · so a second anonymous viewer is still
    // shown the first viewer's lock as a foreign claim. This is the
    // intended trade-off — the alternative (suppress all anon locks)
    // would mask real collisions in public-form / kiosk setups.
    $page = blPage();
    auth()->forgetGuards();

    $pbA = new PageBuilder();
    $pbA->mount(pageId: $page->id);
    $pbA->acquireBlockLock('blk-shared');

    // A separate component instance · models a different browser tab
    // / device. heldBlockLockIds is empty here so the row falls
    // through every filter and shows up.
    $pbB = new PageBuilder();
    $pbB->mount(pageId: $page->id);

    expect($pbB->activeBlockLocks())->toHaveKey('blk-shared')
        ->and($pbB->myBlockLocks())->not->toHaveKey('blk-shared');
});

it('activeBlockLocks suppresses a lock whose holder shares the viewer\'s name · models "another session of mine"', function () {
    // Alice signs in once and claims a block · simulates an earlier
    // browser tab / pre-relogin session.
    $page = blPage();
    blAuthAs(1, 'Alice');
    $pb1 = new PageBuilder();
    $pb1->mount(pageId: $page->id);
    $pb1->acquireBlockLock('blk-1');

    // Same human signs in again from a different device · host app
    // gives them a different id (multi-guard, fresh login, etc.) but
    // the display name is still "Alice". The old lock must NOT show
    // up as "someone else" or we lock Alice out of her own work.
    blAuthAs(99, 'Alice');
    $pb2 = new PageBuilder();
    $pb2->mount(pageId: $page->id);

    expect($pb2->activeBlockLocks())->not->toHaveKey('blk-1');
});

it('an anonymous viewer takeover clears the foreign ribbon · the row stops showing in activeBlockLocks', function () {
    // Reproduces "I clicked Take over but the ribbon stays" · without
    // heldBlockLockIds tracking, an anonymous viewer's new claim
    // (author_id=null) can never be filtered out by the id-match
    // branch (null !== null is false). The component-scoped tracking
    // is the only thing standing between us and a permanently stuck
    // ribbon.
    $page = blPage();

    // Step 1 · some prior anonymous holder leaves a lock behind.
    blAuthAs(1, 'Alice');
    $stale = BlockLock::create([
        'page_id'     => $page->id,
        'block_id'    => 'blk-stuck',
        'author_id'   => null,
        'author_name' => 'Anonymous',
        'expires_at'  => now()->addSeconds(30),
    ]);
    expect($stale->author_id)->toBeNull();

    // Step 2 · the new viewer is anonymous too · would normally be
    // stuck because neither id nor name matches let us suppress the
    // row.
    auth()->forgetGuards();
    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    expect($pb->activeBlockLocks())->toHaveKey('blk-stuck'); // baseline before takeover

    // Step 3 · click Take over.
    expect($pb->takeOverBlockLock('blk-stuck'))->toBeTrue();

    // Step 4 · the ribbon clears AND the green "You editing" path
    // lights up. Both halves are what the user sees on screen.
    expect($pb->activeBlockLocks())->not->toHaveKey('blk-stuck')
        ->and($pb->myBlockLocks())->toHaveKey('blk-stuck');
});

it('takeOverBlockLock replaces the existing row and lets the new viewer claim the block', function () {
    $page = blPage();

    // Alice locks blk-1 then walks away · the row keeps a future expiry
    // (perhaps because a stale tab is still heartbeating it).
    blAuthAs(1, 'Alice');
    $pbA = new PageBuilder();
    $pbA->mount(pageId: $page->id);
    $pbA->acquireBlockLock('blk-1');

    // Bob comes along, sees the ribbon, and clicks "Take over".
    blAuthAs(2, 'Bob');
    $pbB = new PageBuilder();
    $pbB->mount(pageId: $page->id);

    expect($pbB->acquireBlockLock('blk-1'))->toBeFalse();   // baseline · Alice still holds it
    expect($pbB->takeOverBlockLock('blk-1'))->toBeTrue();

    $row = BlockLock::where('page_id', $page->id)->where('block_id', 'blk-1')->first();
    expect($row)->not->toBeNull()
        ->and($row->author_id)->toBe(2)
        ->and($row->author_name)->toBe('Bob')
        ->and($row->expires_at->isFuture())->toBeTrue();

    // The take-over is recorded in the activity feed so an admin can
    // tell apart "legitimate edit" from "lock stolen from someone".
    expect(\LoggedCloud\PageStudio\Models\Activity::where('verb', 'lock_taken_over')->count())->toBe(1);
});

it('ephemeral mode (no pageId) silently no-ops on every lock method', function () {
    blAuthAs(1, 'Alice');

    $pb = new PageBuilder();
    $pb->mount();

    // acquire returns true (success-by-no-op) so the editor still selects.
    expect($pb->acquireBlockLock('blk-1'))->toBeTrue()
        ->and(BlockLock::count())->toBe(0);

    $pb->heartbeatBlockLocks(['blk-1']);
    $pb->releaseBlockLock('blk-1');

    expect(BlockLock::count())->toBe(0)
        ->and($pb->activeBlockLocks())->toBe([]);
});
