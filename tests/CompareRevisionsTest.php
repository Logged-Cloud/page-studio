<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Revision;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('revisionsList returns rows for the bound route ordered desc by id', function () {
    $route = RouteDefinition::create(['name' => 'cr', 'path_template' => '/cr', 'method' => 'get']);
    $r1 = Revision::create(['route_id' => $route->id, 'blocks' => [['type' => 'heading']], 'nodes' => [], 'edges' => []]);
    $r2 = Revision::create(['route_id' => $route->id, 'blocks' => [], 'nodes' => [['id' => 'n1']], 'edges' => []]);
    $r3 = Revision::create(['route_id' => $route->id, 'blocks' => [['type' => 'paragraph'], ['type' => 'heading']], 'nodes' => [], 'edges' => []]);

    $pb = new PageBuilder();
    $pb->mount($route->id);
    $list = $pb->revisionsList();

    expect(array_column($list, 'id'))->toBe([$r3->id, $r2->id, $r1->id])
        ->and($list[0]['block_count'])->toBe(2)
        ->and($list[1]['node_count'])->toBe(1)
        ->and($list[0])->toHaveKeys(['id', 'created_at_iso', 'author_name', 'block_count', 'node_count', 'edge_count']);
});

it('compareRevisions returns both rows + a diff summary', function () {
    $route = RouteDefinition::create(['name' => 'cmp', 'path_template' => '/cmp', 'method' => 'get']);
    $a = Revision::create(['route_id' => $route->id, 'blocks' => [['type' => 'heading']], 'nodes' => [], 'edges' => []]);
    $b = Revision::create([
        'route_id' => $route->id,
        'blocks'   => [['type' => 'h'], ['type' => 'p'], ['type' => 'img'], ['type' => 'btn']],
        'nodes'    => [['id' => 'n1']],
        'edges'    => [],
    ]);

    $pb = new PageBuilder();
    $pb->mount($route->id);
    $cmp = $pb->compareRevisions($a->id, $b->id);

    expect($cmp['a']->id)->toBe($a->id)
        ->and($cmp['b']->id)->toBe($b->id)
        ->and($cmp['diff'])->toBe(['blocks' => 3, 'nodes' => 1, 'edges' => 0]);
});

it('compareRevisions returns nulls when ids are bogus', function () {
    $route = RouteDefinition::create(['name' => 'bog', 'path_template' => '/bog', 'method' => 'get']);
    Revision::create(['route_id' => $route->id, 'blocks' => [], 'nodes' => [], 'edges' => []]);

    $pb = new PageBuilder();
    $pb->mount($route->id);
    $cmp = $pb->compareRevisions(999_999, 888_888);

    expect($cmp['a'])->toBeNull()
        ->and($cmp['b'])->toBeNull()
        ->and($cmp['diff'])->toBe(['blocks' => 0, 'nodes' => 0, 'edges' => 0]);
});

it('compareRevisions refuses revisions from another route', function () {
    $routeA = RouteDefinition::create(['name' => 'a', 'path_template' => '/a', 'method' => 'get']);
    $routeB = RouteDefinition::create(['name' => 'b', 'path_template' => '/b', 'method' => 'get']);

    $mine    = Revision::create(['route_id' => $routeA->id, 'blocks' => [['type' => 'h']], 'nodes' => [], 'edges' => []]);
    $sibling = Revision::create(['route_id' => $routeB->id, 'blocks' => [], 'nodes' => [], 'edges' => []]);

    $pb = new PageBuilder();
    $pb->mount($routeA->id);

    // Sibling row belongs to a different route · must be filtered out so the
    // overlay can't be coerced into showing data from another page.
    $cmp = $pb->compareRevisions($mine->id, $sibling->id);

    expect($cmp['b'])->toBeNull();

    // revisionsList is also route-scoped.
    $ids = array_column($pb->revisionsList(), 'id');
    expect($ids)->toBe([$mine->id])->not->toContain($sibling->id);
});
