<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Support\ModelDiscovery;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * The Model finder node is the only built-in whose settings depend
 * on the host app · the FQCN dropdown is sourced from the discovery
 * cache, and the `finder_key` column should be a select populated
 * from the SELECTED model's `findBy` declaration. This file pins
 * down that wiring.
 */

it('Source\\ModelFinderNode::dynamicSettings turns finder_key into a select sourced from the model\'s declared findBy', function () {
    // Seed a cache with one attributed model that declares two
    // findable columns · the dynamic settings override must turn
    // finder_key into a select populated with those columns.
    $cache = ModelDiscovery::cachePath();
    @mkdir(dirname($cache), 0755, true);
    ModelDiscovery::writeRecordCache([
        'App\\Models\\Customer' => [
            'label'      => 'Customer',
            'findBy'     => ['id', 'email'],
            'searchable' => ['name', 'email'],
        ],
    ], $cache);

    $node = ['type' => 'source.model_finder', 'settings' => ['model_class' => 'App\\Models\\Customer']];
    $instance = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceModelFinderNode();
    $dynamic  = $instance->dynamicSettings($node);

    expect($dynamic)->toHaveKey('finder_key')
        ->and($dynamic['finder_key']['kind'])->toBe('select')
        ->and(array_keys($dynamic['finder_key']['options']))->toBe(['id', 'email']);

    @unlink($cache);
});

it('PageBuilder::selectedNodeSchema merges the node\'s dynamicSettings into the schema · UI surfaces the model-specific finder_key dropdown', function () {
    $cache = ModelDiscovery::cachePath();
    @mkdir(dirname($cache), 0755, true);
    ModelDiscovery::writeRecordCache([
        'App\\Models\\Customer' => [
            'label'      => 'Customer',
            'findBy'     => ['id', 'uuid'],
            'searchable' => [],
        ],
    ], $cache);

    // Re-run the service-provider boot hook so the schema picks up
    // the seeded cache.
    $provider = new \LoggedCloud\PageStudio\PageStudioServiceProvider(app());
    $ref = new ReflectionMethod($provider, 'injectModelOptions');
    $ref->setAccessible(true);
    $ref->invoke($provider);

    $pb = new PageBuilder();
    $pb->mount();
    $pb->nodes = [[
        'id'       => 'n1',
        'type'     => 'source.model_finder',
        'settings' => ['model_class' => 'App\\Models\\Customer', 'finder_key' => 'id'],
        'position' => ['x' => 0, 'y' => 0],
    ]];
    $pb->selectedNodeId = 'n1';

    $schema = $pb->selectedNodeSchema();

    expect($schema['settings']['finder_key']['kind'])->toBe('select')
        ->and(array_keys($schema['settings']['finder_key']['options']))->toBe(['id', 'uuid']);

    @unlink($cache);
});

it('dynamicSettings is null when no model is selected · leaves the bare text field default in place', function () {
    $node = ['type' => 'source.model_finder', 'settings' => ['model_class' => '']];
    $instance = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceModelFinderNode();

    expect($instance->dynamicSettings($node))->toBeNull();
});

it('dynamicSettings is null when the selected model has no findBy entry · leaves the bare text field default in place', function () {
    // Selected model exists in the cache but didn't declare findBy
    // explicitly. We don't want to render an empty select.
    $cache = ModelDiscovery::cachePath();
    @mkdir(dirname($cache), 0755, true);
    ModelDiscovery::writeRecordCache([
        'App\\Models\\Booking' => [
            'label'      => 'Booking',
            'findBy'     => [],
            'searchable' => [],
        ],
    ], $cache);

    $node = ['type' => 'source.model_finder', 'settings' => ['model_class' => 'App\\Models\\Booking']];
    $instance = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceModelFinderNode();

    expect($instance->dynamicSettings($node))->toBeNull();

    @unlink($cache);
});
