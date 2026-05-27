<?php

use LoggedCloud\PageStudio\Nodes\Builtin\SourceRandomNode;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('source.random emits a number inside the min/max range', function () {
    $node = new SourceRandomNode();
    for ($i = 0; $i < 20; $i++) {
        $v = $node->evaluate([], ['min' => 10, 'max' => 20, 'integer' => false, 'seed' => null], [])['value'];
        expect($v)->toBeGreaterThanOrEqual(10.0)
            ->and($v)->toBeLessThanOrEqual(20.0);
    }
});

it('source.random returns an integer when integer=true', function () {
    $node = new SourceRandomNode();
    for ($i = 0; $i < 20; $i++) {
        $v = $node->evaluate([], ['min' => 1, 'max' => 6, 'integer' => true, 'seed' => null], [])['value'];
        expect((int) $v)->toBe($v); // no fractional part
        expect($v)->toBeGreaterThanOrEqual(1)
            ->and($v)->toBeLessThanOrEqual(6);
    }
});

it('source.random with a seed is deterministic', function () {
    $node = new SourceRandomNode();
    $a = $node->evaluate([], ['min' => 0, 'max' => 1000, 'integer' => false, 'seed' => 42], [])['value'];
    $b = $node->evaluate([], ['min' => 0, 'max' => 1000, 'integer' => false, 'seed' => 42], [])['value'];
    $c = $node->evaluate([], ['min' => 0, 'max' => 1000, 'integer' => false, 'seed' => 99], [])['value'];

    expect($a)->toBe($b)
        ->and($a)->not->toBe($c);
});

it('source.random with min/max wired through the engine respects the wired range', function () {
    $nodes = [
        ['id' => 'lo',  'type' => 'source.float',  'settings' => ['value' => 100]],
        ['id' => 'hi',  'type' => 'source.float',  'settings' => ['value' => 200]],
        ['id' => 'rnd', 'type' => 'source.random', 'settings' => ['min' => 0, 'max' => 0, 'integer' => false, 'seed' => 7]],
        ['id' => 'out', 'type' => 'output',        'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'lo',  'from_socket' => 'value', 'to_node' => 'rnd', 'to_socket' => 'min'],
        ['id' => 'e2', 'from_node' => 'hi',  'from_socket' => 'value', 'to_node' => 'rnd', 'to_socket' => 'max'],
        ['id' => 'e3', 'from_node' => 'rnd', 'from_socket' => 'value', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['r'])->toBeGreaterThanOrEqual(100.0)
        ->and($ctx['r'])->toBeLessThanOrEqual(200.0);
});
