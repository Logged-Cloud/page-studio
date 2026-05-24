<?php

use Illuminate\Foundation\Auth\User;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Activity;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

function afAuthAs(int $id, string $name): User
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

function afPage(): Page
{
    return Page::create(['blocks' => [], 'status' => 'draft']);
}

it('save() persists a "saved" activity row', function () {
    afAuthAs(1, 'Alice');
    $page = afPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $pb->save();

    $rows = Activity::all();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->verb)->toBe('saved')
        ->and($rows[0]->author_name)->toBe('Alice')
        ->and($rows[0]->page_id)->toBe($page->id);
});

it('publish() and unpublish() record their own verbs (no duplicate saved row)', function () {
    afAuthAs(1, 'Alice');
    $page = afPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);

    $pb->publish();
    $pb->unpublish();

    $verbs = Activity::orderBy('id')->pluck('verb')->all();
    expect($verbs)->toBe(['published', 'unpublished']);
});

it('addComment writes a comment_added row with the block id in payload', function () {
    afAuthAs(1, 'Alice');
    $page = afPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $pb->addComment('blk-1', 'Looks great!', 'Heading');

    $row = Activity::where('verb', 'comment_added')->first();
    expect($row)->not->toBeNull()
        ->and($row->payload['block_id'] ?? null)->toBe('blk-1')
        ->and($row->payload['body'] ?? null)->toBe('Looks great!');
});

it('resolveComment writes a comment_resolved row referencing the original', function () {
    afAuthAs(1, 'Alice');
    $page = afPage();

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $pb->addComment('blk-1', 'Needs work', 'Heading');

    $original = Activity::where('verb', 'comment_added')->first();
    afAuthAs(2, 'Bob');
    $pb2 = new PageBuilder();
    $pb2->mount(pageId: $page->id);
    $pb2->resolveComment($original->id);

    $row = Activity::where('verb', 'comment_resolved')->first();
    expect($row)->not->toBeNull()
        ->and($row->author_name)->toBe('Bob')
        ->and($row->payload['resolved_id'] ?? null)->toBe($original->id);
});

it('activityFeed returns rows most-recent-first, capped at 30', function () {
    afAuthAs(1, 'Alice');
    $page = afPage();

    // Seed 35 rows · only the newest 30 should come back.
    for ($i = 0; $i < 35; $i++) {
        Activity::create([
            'page_id'     => $page->id,
            'verb'        => 'saved',
            'author_id'   => 1,
            'author_name' => 'Alice',
            'created_at'  => now()->subMinutes(35 - $i),
            'updated_at'  => now()->subMinutes(35 - $i),
        ]);
    }

    $pb = new PageBuilder();
    $pb->mount(pageId: $page->id);
    $feed = $pb->activityFeed();

    expect($feed)->toHaveCount(30);
    // Most recent created_at should sort to the top.
    expect($feed[0]['created_at'])->toBeGreaterThan($feed[29]['created_at']);
});

it('activityFeed includes both routeId-bound and pageId-bound history', function () {
    afAuthAs(1, 'Alice');

    $route = RouteDefinition::create([
        'name'          => 'demo',
        'path_template' => '/demo',
    ]);
    $page = Page::create(['blocks' => [], 'status' => 'draft', 'route_id' => $route->id]);

    // Two rows · one bound to page, one bound only to route.
    Activity::create([
        'page_id'     => $page->id,
        'verb'        => 'saved',
        'author_name' => 'Alice',
    ]);
    Activity::create([
        'route_id'    => $route->id,
        'verb'        => 'published',
        'author_name' => 'Alice',
    ]);

    $pb = new PageBuilder();
    $pb->mount(routeId: $route->id, pageId: $page->id);
    $feed = $pb->activityFeed();

    $verbs = array_column($feed, 'verb');
    expect($verbs)->toContain('saved')
        ->and($verbs)->toContain('published');
});

it('ephemeral mode (no binding) writes no activity rows and returns an empty feed', function () {
    afAuthAs(1, 'Alice');

    $pb = new PageBuilder();
    $pb->mount();

    $pb->save();
    $pb->publish();
    $pb->unpublish();
    $pb->addComment('blk-1', 'hi', 'Heading');

    expect(Activity::count())->toBe(0)
        ->and($pb->activityFeed())->toBe([]);
});
