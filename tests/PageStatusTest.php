<?php

use Illuminate\Support\Carbon;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('published scope returns only status=published with no future publish_at', function () {
    $route = RouteDefinition::create(['name' => 'r', 'path_template' => '/r', 'method' => 'get']);
    $pub = Page::create(['route_id' => $route->id, 'status' => 'published', 'blocks' => []]);
    Page::create(['route_id' => null, 'status' => 'draft', 'blocks' => []]);

    $ids = Page::published()->pluck('id')->all();
    expect($ids)->toBe([$pub->id]);
});

it('published scope includes pages with publish_at in the past', function () {
    $route = RouteDefinition::create(['name' => 'past', 'path_template' => '/past', 'method' => 'get']);
    $p = Page::create([
        'route_id'   => $route->id,
        'status'     => 'published',
        'publish_at' => Carbon::now()->subHour(),
        'blocks'     => [],
    ]);

    expect(Page::published()->pluck('id')->all())->toContain($p->id);
});

it('published scope excludes pages with publish_at in the future', function () {
    $route = RouteDefinition::create(['name' => 'fut', 'path_template' => '/fut', 'method' => 'get']);
    $p = Page::create([
        'route_id'   => $route->id,
        'status'     => 'published',
        'publish_at' => Carbon::now()->addHour(),
        'blocks'     => [],
    ]);

    expect(Page::published()->pluck('id')->all())->not->toContain($p->id);
});

it('PageBuilder::publish() flips status + stamps published_at', function () {
    $route = RouteDefinition::create(['name' => 'p1', 'path_template' => '/p1', 'method' => 'get']);
    $page  = Page::create(['route_id' => $route->id, 'status' => 'draft', 'blocks' => []]);

    $pb = \Livewire\Livewire::test(PageBuilder::class, ['routeId' => $route->id])
        ->call('publish');

    $page->refresh();
    expect($page->status)->toBe('published')
        ->and($page->published_at)->not->toBeNull();
    $pb->assertSet('status', 'published');
});

it('PageBuilder::unpublish() flips back to draft', function () {
    $route = RouteDefinition::create(['name' => 'up', 'path_template' => '/up', 'method' => 'get']);
    Page::create([
        'route_id'     => $route->id,
        'status'       => 'published',
        'published_at' => Carbon::now()->subDay(),
        'blocks'       => [],
    ]);

    \Livewire\Livewire::test(PageBuilder::class, ['routeId' => $route->id])
        ->call('unpublish');

    expect(Page::where('route_id', $route->id)->first()->status)->toBe('draft');
});

it('save() persists status + publishAt', function () {
    $route = RouteDefinition::create(['name' => 'sv', 'path_template' => '/sv', 'method' => 'get']);
    Page::create(['route_id' => $route->id, 'status' => 'draft', 'blocks' => []]);

    $whenIso = Carbon::now()->addDay()->startOfMinute()->format('Y-m-d\TH:i');

    \Livewire\Livewire::test(PageBuilder::class, ['routeId' => $route->id])
        ->set('status', 'published')
        ->set('publishAt', $whenIso)
        ->call('save');

    $stored = Page::where('route_id', $route->id)->first();
    expect($stored->status)->toBe('published')
        ->and($stored->publish_at)->not->toBeNull()
        ->and($stored->publish_at->format('Y-m-d\TH:i'))->toBe($whenIso);
});

it('service-provider auto-route returns 404 for draft pages', function () {
    $route = RouteDefinition::create(['name' => 'draft_route', 'path_template' => '/draft-only', 'method' => 'get']);
    Page::create(['route_id' => $route->id, 'status' => 'draft', 'blocks' => []]);

    // Re-trigger route registration so the new RouteDefinition is bound.
    $provider = new \LoggedCloud\PageStudio\PageStudioServiceProvider(app());
    $ref = new ReflectionMethod($provider, 'registerPageRoutes');
    $ref->setAccessible(true);
    $ref->invoke($provider);

    $this->get('/draft-only')->assertStatus(404);
});

it('service-provider auto-route returns 404 for future scheduled pages', function () {
    $route = RouteDefinition::create(['name' => 'sched_route', 'path_template' => '/sched-only', 'method' => 'get']);
    Page::create([
        'route_id'   => $route->id,
        'status'     => 'published',
        'publish_at' => Carbon::now()->addHour(),
        'blocks'     => [],
    ]);

    $provider = new \LoggedCloud\PageStudio\PageStudioServiceProvider(app());
    $ref = new ReflectionMethod($provider, 'registerPageRoutes');
    $ref->setAccessible(true);
    $ref->invoke($provider);

    $this->get('/sched-only')->assertStatus(404);
});
