<?php

use LoggedCloud\PageStudio\Nodes\Examples\GreetingNode;
use LoggedCloud\PageStudio\Nodes\NodeRegistry;
use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    NodeRegistry::clear();
    // Restore the two built-ins these tests compose graphs from · the
    // service provider's full registerBuiltinNodes already ran at boot
    // but `clear()` wipes it, so we put back the minimum the tests need.
    NodeRegistry::register(\LoggedCloud\PageStudio\Nodes\Builtin\SourceConstantNode::class);
    NodeRegistry::register(\LoggedCloud\PageStudio\Nodes\Builtin\OutputNode::class);
});

it('registers a NodeType subclass under its declared key', function () {
    NodeRegistry::register(GreetingNode::class);
    expect(NodeRegistry::all())->toHaveKey('custom.greeting')
        ->and(NodeRegistry::find('custom.greeting'))->toBe(GreetingNode::class);
});

it('refuses non-NodeType classes', function () {
    NodeRegistry::register(\stdClass::class);
})->throws(InvalidArgumentException::class);

it('emits the library entry shape that the palette + canvas read', function () {
    $entry = GreetingNode::toLibraryEntry();
    expect($entry)
        ->toHaveKeys(['group', 'label', 'icon', 'inputs', 'outputs', 'settings', 'custom', 'class'])
        ->and($entry['class'])->toBe(GreetingNode::class)
        ->and($entry['inputs']['name']['type'])->toBe('string')
        ->and($entry['outputs']['value']['type'])->toBe('string');
});

it('engine routes execution to the registered class', function () {
    NodeRegistry::register(GreetingNode::class);
    config(['page-studio.nodes' => array_merge(
        config('page-studio.nodes', []),
        ['custom.greeting' => GreetingNode::toLibraryEntry()],
    )]);

    $nodes = [
        ['id' => 'n', 'type' => 'source.constant', 'settings' => ['value' => 'Alice']],
        ['id' => 'g', 'type' => 'custom.greeting', 'settings' => ['greeting' => 'Hi']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'salutation']],
    ];
    $edges = [
        ['from_node' => 'n', 'from_socket' => 'value', 'to_node' => 'g', 'to_socket' => 'name'],
        ['from_node' => 'g', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['salutation'])->toBe('Hi, Alice!');
});

it('falls back to legacy template-based custom nodes when no class is registered', function () {
    // No registration · the engine should look up the legacy schema instead.
    $lib = config('page-studio.nodes', []);
    $lib['custom.legacy'] = [
        'group'    => 'transform',
        'label'    => 'Legacy',
        'icon'     => '?',
        'inputs'   => ['name' => ['label' => 'Name', 'type' => 'string']],
        'outputs'  => ['value' => ['label' => 'Out', 'type' => 'string']],
        'settings' => [],
        'custom'   => true,
        'template' => 'Welcome, {{ inputs.name }}',
    ];
    config(['page-studio.nodes' => $lib]);

    $nodes = [
        ['id' => 'n', 'type' => 'source.constant', 'settings' => ['value' => 'Bob']],
        ['id' => 'l', 'type' => 'custom.legacy',   'settings' => []],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'msg']],
    ];
    $edges = [
        ['from_node' => 'n', 'from_socket' => 'value', 'to_node' => 'l', 'to_socket' => 'name'],
        ['from_node' => 'l', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['msg'])->toBe('Welcome, Bob');
});
