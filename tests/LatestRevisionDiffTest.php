<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('returns zeros when no revision exists yet', function () {
    $route = RouteDefinition::create(['name' => 'r1', 'path_template' => '/x', 'method' => 'get']);
    $pb = new PageBuilder();
    $pb->mount($route->id);

    expect($pb->latestRevisionDiff)->toBe(['blocks' => 0, 'nodes' => 0, 'edges' => 0]);
});

it('reports a positive blocks delta after adding a block on top of a saved revision', function () {
    $route = RouteDefinition::create(['name' => 'r2', 'path_template' => '/y', 'method' => 'get']);
    $pb = new PageBuilder();
    $pb->mount($route->id);

    $pb->blocks = [BlockFactory::make('heading')];
    $pb->save();

    // Add another block · should now be one block ahead of the revision.
    $pb->blocks[] = BlockFactory::make('paragraph');

    expect($pb->latestRevisionDiff['blocks'])->toBe(1);
});

it('reports a negative blocks delta after removing a block from a saved revision', function () {
    $route = RouteDefinition::create(['name' => 'r3', 'path_template' => '/z', 'method' => 'get']);
    $pb = new PageBuilder();
    $pb->mount($route->id);

    $pb->blocks = [BlockFactory::make('heading'), BlockFactory::make('paragraph')];
    $pb->save();

    array_pop($pb->blocks);

    expect($pb->latestRevisionDiff['blocks'])->toBe(-1);
});

it('does not crash in ephemeral mode (no route, no revisions)', function () {
    $pb = new PageBuilder();
    $pb->mount();

    expect($pb->latestRevisionDiff)->toBe(['blocks' => 0, 'nodes' => 0, 'edges' => 0]);
});

it('returns zeros when a route is bound but no revision was ever snapshotted', function () {
    $route = RouteDefinition::create(['name' => 'r4', 'path_template' => '/q', 'method' => 'get']);
    $pb = new PageBuilder();
    $pb->mount($route->id);

    // Mutate without saving.
    $pb->blocks = [BlockFactory::make('heading')];

    expect($pb->latestRevisionDiff)->toBe(['blocks' => 0, 'nodes' => 0, 'edges' => 0]);
});
