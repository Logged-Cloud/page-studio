<?php

use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Typed constants + math nodes filling the Blender Constants /
 * Math · Comparison / MinMax / Rounding / Mix / Boolean gaps.
 */

// ─── Typed constants ──────────────────────────────────────────────

it('source.bool outputs a boolean from its setting', function () {
    $on  = (new \LoggedCloud\PageStudio\Nodes\Builtin\SourceBoolNode())->evaluate([], ['value' => true],  []);
    $off = (new \LoggedCloud\PageStudio\Nodes\Builtin\SourceBoolNode())->evaluate([], ['value' => false], []);
    expect($on['value'])->toBeTrue()
        ->and($off['value'])->toBeFalse();
});

it('source.int outputs an integer (truncates decimals)', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceIntNode();
    expect($node->evaluate([], ['value' => '42'],   [])['value'])->toBe(42)
        ->and($node->evaluate([], ['value' => 3.7], [])['value'])->toBe(3);
});

it('source.float outputs a float', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceFloatNode();
    expect($node->evaluate([], ['value' => '3.14'], [])['value'])->toBe(3.14);
});

it('source.vector outputs an x/y/z array', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\SourceVectorNode();
    $out  = $node->evaluate([], ['x' => 1, 'y' => 2, 'z' => 3], []);
    expect($out['value'])->toBe(['x' => 1.0, 'y' => 2.0, 'z' => 3.0]);
});

// ─── Math · comparison ────────────────────────────────────────────

it('transform.compare evaluates each operator', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformCompareNode();
    $cases = [
        ['<',  3, 5, true],
        ['<',  5, 3, false],
        ['<=', 5, 5, true],
        ['>',  5, 3, true],
        ['>=', 5, 5, true],
        ['=',  5, 5, true],
        ['!=', 5, 6, true],
    ];
    foreach ($cases as [$op, $a, $b, $want]) {
        $got = $node->evaluate(['a' => $a, 'b' => $b], ['op' => $op], [])['value'];
        expect($got)->toBe($want, "$a $op $b expected ".var_export($want, true)." got ".var_export($got, true));
    }
});

// ─── Math · clamp ─────────────────────────────────────────────────

it('transform.clamp clamps the value between min and max', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformClampNode();
    expect($node->evaluate(['value' => 15], ['min' => 0, 'max' => 10], [])['value'])->toBe(10.0)
        ->and($node->evaluate(['value' => -5], ['min' => 0, 'max' => 10], [])['value'])->toBe(0.0)
        ->and($node->evaluate(['value' => 5],  ['min' => 0, 'max' => 10], [])['value'])->toBe(5.0);
});

// ─── Math · rounding ──────────────────────────────────────────────

it('transform.round supports round / floor / ceil / abs / sign', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformRoundNode();
    expect($node->evaluate(['value' => 3.7], ['op' => 'round'], [])['value'])->toBe(4.0)
        ->and($node->evaluate(['value' => 3.7], ['op' => 'floor'], [])['value'])->toBe(3.0)
        ->and($node->evaluate(['value' => 3.2], ['op' => 'ceil'],  [])['value'])->toBe(4.0)
        ->and($node->evaluate(['value' => -7],  ['op' => 'abs'],   [])['value'])->toBe(7.0)
        ->and($node->evaluate(['value' => -7],  ['op' => 'sign'],  [])['value'])->toBe(-1.0)
        ->and($node->evaluate(['value' => 7],   ['op' => 'sign'],  [])['value'])->toBe(1.0)
        ->and($node->evaluate(['value' => 0],   ['op' => 'sign'],  [])['value'])->toBe(0.0);
});

// ─── Boolean ops ──────────────────────────────────────────────────

it('transform.boolean supports AND / OR / NOT / XOR', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformBooleanNode();
    expect($node->evaluate(['a' => true,  'b' => false], ['op' => 'and'], [])['value'])->toBeFalse()
        ->and($node->evaluate(['a' => true,  'b' => false], ['op' => 'or'],  [])['value'])->toBeTrue()
        ->and($node->evaluate(['a' => true,  'b' => false], ['op' => 'xor'], [])['value'])->toBeTrue()
        ->and($node->evaluate(['a' => true,  'b' => true],  ['op' => 'xor'], [])['value'])->toBeFalse()
        ->and($node->evaluate(['a' => true],                 ['op' => 'not'], [])['value'])->toBeFalse()
        ->and($node->evaluate(['a' => false],                ['op' => 'not'], [])['value'])->toBeTrue();
});

// ─── Math · mix ───────────────────────────────────────────────────

it('transform.mix blends a and b by factor', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformMixNode();
    expect($node->evaluate(['a' => 0,  'b' => 100, 'factor' => 0],    [], [])['value'])->toBe(0.0)
        ->and($node->evaluate(['a' => 0,  'b' => 100, 'factor' => 1],    [], [])['value'])->toBe(100.0)
        ->and($node->evaluate(['a' => 0,  'b' => 100, 'factor' => 0.25], [], [])['value'])->toBe(25.0);
});

// ─── String · substring ───────────────────────────────────────────

it('transform.substring slices a string', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformSubstringNode();
    expect($node->evaluate(['text' => 'Hello, world'], ['start' => 7, 'length' => 5], [])['value'])->toBe('world')
        ->and($node->evaluate(['text' => 'Hello'],    ['start' => 0, 'length' => 100], [])['value'])->toBe('Hello');
});

// ─── Math · min/max ───────────────────────────────────────────────

it('transform.min_max returns the smaller or larger value', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\TransformMinMaxNode();
    expect($node->evaluate(['a' => 3, 'b' => 7], ['op' => 'min'], [])['value'])->toBe(3.0)
        ->and($node->evaluate(['a' => 3, 'b' => 7], ['op' => 'max'], [])['value'])->toBe(7.0);
});

// ─── End-to-end · all nodes wired in the engine ───────────────────

it('the new nodes wire through the engine with settings-as-input merging', function () {
    // source.float -> transform.clamp{min, max wired} -> output
    $nodes = [
        ['id' => 'v',    'type' => 'source.float',    'settings' => ['value' => 200]],
        ['id' => 'lo',   'type' => 'source.float',    'settings' => ['value' => 0]],
        ['id' => 'hi',   'type' => 'source.float',    'settings' => ['value' => 100]],
        ['id' => 'clmp', 'type' => 'transform.clamp', 'settings' => ['min' => 0, 'max' => 0]],
        ['id' => 'out',  'type' => 'output',          'settings' => ['name' => 'capped']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'v',    'from_socket' => 'value', 'to_node' => 'clmp', 'to_socket' => 'value'],
        ['id' => 'e2', 'from_node' => 'lo',   'from_socket' => 'value', 'to_node' => 'clmp', 'to_socket' => 'min'],
        ['id' => 'e3', 'from_node' => 'hi',   'from_socket' => 'value', 'to_node' => 'clmp', 'to_socket' => 'max'],
        ['id' => 'e4', 'from_node' => 'clmp', 'from_socket' => 'value', 'to_node' => 'out',  'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['capped'])->toBe(100.0);
});
