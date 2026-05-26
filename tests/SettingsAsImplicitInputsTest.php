<?php

use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Blender-style · every settable field is implicitly an input
 * socket. Wiring an edge to a setting's name overrides the static
 * value for that evaluation, fallback to the static value when
 * nothing's wired. Per-node opt-in not required.
 *
 * The engine takes care of the merge before calling each node's
 * evaluate(), so existing nodes that read $settings['x'] keep
 * working unchanged.
 */

it('an edge targeting a settable field overrides the static setting value', function () {
    // source.constant has a `value` setting. A second constant wired
    // INTO its value socket should override what it emits.
    $nodes = [
        ['id' => 'wired', 'type' => 'source.constant', 'settings' => ['value' => '5']],
        ['id' => 'stat',  'type' => 'source.constant', 'settings' => ['value' => 'original']],
        ['id' => 'out',   'type' => 'output',          'settings' => ['name' => 'result']],
    ];
    $edges = [
        // wired -> stat.value · should override 'original' with '5'.
        ['id' => 'e1', 'from_node' => 'wired', 'from_socket' => 'value', 'to_node' => 'stat', 'to_socket' => 'value'],
        ['id' => 'e2', 'from_node' => 'stat',  'from_socket' => 'value', 'to_node' => 'out',  'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['result'])->toBe('5');
});

it('the static setting still applies when nothing is wired to it', function () {
    $nodes = [
        ['id' => 'src', 'type' => 'source.constant', 'settings' => ['value' => 'untouched']],
        ['id' => 'out', 'type' => 'output',          'settings' => ['name' => 'result']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'src', 'from_socket' => 'value', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['result'])->toBe('untouched');
});

it('an edge that targets a settings field of a downstream node propagates the wired value through to evaluate', function () {
    // transform.format_date has settings: format, offset_amount,
    // offset_unit. Wire a constant into `format` to override the
    // static 'Y-m-d' setting · the date should render in the wired
    // format instead.
    $nodes = [
        ['id' => 'now',  'type' => 'source.now',       'settings' => []],
        ['id' => 'fmt',  'type' => 'source.constant',  'settings' => ['value' => 'Y']], // just the year
        ['id' => 'fd',   'type' => 'transform.format_date', 'settings' => ['format' => 'Y-m-d H:i:s', 'offset_amount' => 0, 'offset_unit' => 'days']],
        ['id' => 'out',  'type' => 'output',           'settings' => ['name' => 'year']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'now', 'from_socket' => 'value', 'to_node' => 'fd',  'to_socket' => 'value'],
        ['id' => 'e2', 'from_node' => 'fmt', 'from_socket' => 'value', 'to_node' => 'fd',  'to_socket' => 'format'],
        ['id' => 'e3', 'from_node' => 'fd',  'from_socket' => 'value', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    // Should be just a 4-digit year because we wired Y into format.
    expect($ctx['year'])->toMatch('/^\d{4}$/');
});

it('a null-valued wire does NOT override the static setting · keeps placeholder edges harmless', function () {
    // A wire from an upstream that produced null shouldn't wipe out
    // a perfectly good static setting. Reproduces the case of a
    // route_variable that hasn't been bound.
    $nodes = [
        ['id' => 'nul', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'doesNotExist']],
        ['id' => 'src', 'type' => 'source.constant',       'settings' => ['value' => 'kept']],
        ['id' => 'out', 'type' => 'output',                'settings' => ['name' => 'result']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'nul', 'from_socket' => 'value', 'to_node' => 'src', 'to_socket' => 'value'],
        ['id' => 'e2', 'from_node' => 'src', 'from_socket' => 'value', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['result'])->toBe('kept');
});
