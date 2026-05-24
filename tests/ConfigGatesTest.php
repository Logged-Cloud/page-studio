<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
});

it('hides disabled node types from the node library', function () {
    config(['page-studio.disabled_nodes' => ['source.constant', 'transform.uppercase']]);

    $pb = new PageBuilder();
    $grouped = $pb->nodeLibrary();

    $flat = collect($grouped)->flatMap(fn ($g) => array_keys($g))->all();
    expect($flat)->not->toContain('source.constant')
        ->and($flat)->not->toContain('transform.uppercase')
        ->and($flat)->toContain('source.route_variable');
});

it('hides disabled block types from the block library', function () {
    config(['page-studio.disabled_blocks' => ['code', 'quote']]);

    $pb = new PageBuilder();
    $grouped = $pb->blockLibrary();

    $flat = collect($grouped)->flatMap(fn ($g) => array_keys($g))->all();
    expect($flat)->not->toContain('code')
        ->and($flat)->not->toContain('quote')
        ->and($flat)->toContain('heading');
});

it('refuses to add disabled nodes via addNode()', function () {
    $route = RouteDefinition::create(['name' => 'gate-test', 'path_template' => '/gate-test', 'method' => 'get']);
    config(['page-studio.disabled_nodes' => ['source.constant']]);

    $pb = new PageBuilder();
    $pb->mount($route->id);
    $before = count($pb->nodes);
    $pb->addNode('source.constant');

    expect($pb->nodes)->toHaveCount($before);
});

it('forces the drawer closed when the node editor is disabled', function () {
    $route = RouteDefinition::create(['name' => 'no-editor', 'path_template' => '/no-editor', 'method' => 'get']);
    config(['page-studio.enable_node_editor' => false]);

    $pb = new PageBuilder();
    $pb->mount($route->id);

    expect($pb->drawerOpen)->toBeFalse()
        ->and($pb->nodeEditorEnabled())->toBeFalse();
});

it('refuses node mutations when the editor is disabled', function () {
    $route = RouteDefinition::create(['name' => 'no-editor-2', 'path_template' => '/no-editor-2', 'method' => 'get']);
    config(['page-studio.enable_node_editor' => false]);

    $pb = new PageBuilder();
    $pb->mount($route->id);

    $pb->addNode('source.constant');
    $pb->toggleDrawer();

    expect($pb->nodes)->toBeEmpty()
        ->and($pb->drawerOpen)->toBeFalse();
});

it('defaults to enabled with no disabled types', function () {
    $pb = new PageBuilder();
    expect($pb->nodeEditorEnabled())->toBeTrue()
        ->and(config('page-studio.disabled_nodes'))->toBe([])
        ->and(config('page-studio.disabled_blocks'))->toBe([]);
});
