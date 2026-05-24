<?php

use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

test('empty graph returns the base context unchanged', function () {
    expect(NodeGraphEngine::evaluate([], [], ['x' => 1]))->toBe(['x' => 1]);
});

test('route_variable source reads from the base context', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'userId']],
        ['id' => 'o', 'type' => 'output',               'settings' => ['name' => 'echoed']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, ['userId' => '42']);
    expect($ctx['echoed'])->toBe('42');
});

test('uppercase transform converts the upstream value', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',   'settings' => ['value' => 'hello']],
        ['id' => 't', 'type' => 'transform.uppercase', 'settings' => []],
        ['id' => 'o', 'type' => 'output',             'settings' => ['name' => 'big']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['big'])->toBe('HELLO');
});

test('concat combines two upstream inputs with the separator', function () {
    $nodes = [
        ['id' => 'a', 'type' => 'source.constant',  'settings' => ['value' => 'foo']],
        ['id' => 'b', 'type' => 'source.constant',  'settings' => ['value' => 'bar']],
        ['id' => 'c', 'type' => 'transform.concat', 'settings' => ['separator' => '-']],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'joined']],
    ];
    $edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'a'],
        ['from_node' => 'b', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'b'],
        ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['joined'])->toBe('foo-bar');
});

test('default-when-empty substitutes a fallback', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'maybe']],
        ['id' => 'd', 'type' => 'transform.default',     'settings' => ['fallback' => '(none)']],
        ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'resolved']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'd', 'to_socket' => 'value'],
        ['from_node' => 'd', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, ['maybe' => null])['resolved'])->toBe('(none)');
    expect(NodeGraphEngine::evaluate($nodes, $edges, ['maybe' => 'real'])['resolved'])->toBe('real');
});

test('read-field walks data_get-style paths through arrays + objects', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'profile']],
        ['id' => 'f', 'type' => 'transform.field',       'settings' => ['field' => 'name']],
        ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'displayName']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'f', 'to_socket' => 'object'],
        ['from_node' => 'f', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, ['profile' => ['name' => 'Charles']]);
    expect($ctx['displayName'])->toBe('Charles');
});

test('slugify lowercases and dashes', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',     'settings' => ['value' => 'Hello World!']],
        ['id' => 't', 'type' => 'transform.slugify',   'settings' => []],
        ['id' => 'o', 'type' => 'output',              'settings' => ['name' => 'slug']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['slug'])->toBe('hello-world');
});

test('format-date pulls an ISO string through a custom format', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',      'settings' => ['value' => '2026-05-23T14:30:00Z']],
        ['id' => 't', 'type' => 'transform.format_date', 'settings' => ['format' => 'd M Y']],
        ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'formatted']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'value'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['formatted'])->toBe('23 May 2026');
});

test('output node with empty / invalid name is ignored', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'x']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => '  ']], // blank
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx)->not->toHaveKey('');
});

test('topological order respects dependencies even when nodes are passed reversed', function () {
    $nodes = [
        ['id' => 'o', 'type' => 'output',             'settings' => ['name' => 'out']],
        ['id' => 't', 'type' => 'transform.uppercase', 'settings' => []],
        ['id' => 's', 'type' => 'source.constant',    'settings' => ['value' => 'go']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['out'])->toBe('GO');
});

test('chained outputs feed each other through the merged context', function () {
    $nodes = [
        ['id' => 'a', 'type' => 'source.constant',  'settings' => ['value' => 'hi']],
        ['id' => 'oa','type' => 'output',           'settings' => ['name' => 'greet']],
        // Second graph leg reads the first output via route_variable lookup.
        ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'greet']],
        ['id' => 'u', 'type' => 'transform.uppercase',   'settings' => []],
        ['id' => 'ob','type' => 'output',                 'settings' => ['name' => 'shout']],
    ];
    $edges = [
        ['from_node' => 'a',  'from_socket' => 'value', 'to_node' => 'oa', 'to_socket' => 'value'],
        ['from_node' => 's',  'from_socket' => 'value', 'to_node' => 'u',  'to_socket' => 'text'],
        ['from_node' => 'u',  'from_socket' => 'value', 'to_node' => 'ob', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['greet'])->toBe('hi');
    expect($ctx['shout'])->toBe('HI');
});

test('cycle does not loop forever · breaks the back-edge and evaluates the rest', function () {
    $nodes = [
        ['id' => 'a', 'type' => 'transform.uppercase', 'settings' => []],
        ['id' => 'b', 'type' => 'transform.lowercase', 'settings' => []],
        ['id' => 'src', 'type' => 'source.constant',   'settings' => ['value' => 'seed']],
        ['id' => 'o', 'type' => 'output',              'settings' => ['name' => 'result']],
    ];
    $edges = [
        // a → b → a is a cycle; we still want the rest to run.
        ['from_node' => 'a',   'from_socket' => 'value', 'to_node' => 'b', 'to_socket' => 'text'],
        ['from_node' => 'b',   'from_socket' => 'value', 'to_node' => 'a', 'to_socket' => 'text'],
        ['from_node' => 'src', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['result'])->toBe('seed');
});

test('trim removes surrounding whitespace', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',    'settings' => ['value' => '   hi   ']],
        ['id' => 't', 'type' => 'transform.trim',     'settings' => []],
        ['id' => 'o', 'type' => 'output',             'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe('hi');
});

test('replace swaps a substring', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'hello world']],
        ['id' => 't', 'type' => 'transform.replace', 'settings' => ['find' => 'world', 'replace' => 'there']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe('hello there');
});

test('length returns the count of strings AND arrays', function () {
    $for = function (string $rawVarName, mixed $contextValue) {
        $nodes = [
            ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => $rawVarName]],
            ['id' => 't', 'type' => 'transform.length',      'settings' => []],
            ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'r']],
        ];
        $edges = [
            ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'value'],
            ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
        ];
        return NodeGraphEngine::evaluate($nodes, $edges, [$rawVarName => $contextValue])['r'];
    };
    expect($for('s', 'hello'))->toBe(5);
    expect($for('a', ['a', 'b', 'c', 'd']))->toBe(4);
});

test('split and join round-trip', function () {
    $nodes = [
        ['id' => 's',  'type' => 'source.constant',    'settings' => ['value' => 'a,b,c,d']],
        ['id' => 'sp', 'type' => 'transform.split',    'settings' => ['delimiter' => ',']],
        ['id' => 'jn', 'type' => 'transform.join',     'settings' => ['separator' => ' | ']],
        ['id' => 'o',  'type' => 'output',             'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's',  'from_socket' => 'value', 'to_node' => 'sp', 'to_socket' => 'text'],
        ['from_node' => 'sp', 'from_socket' => 'value', 'to_node' => 'jn', 'to_socket' => 'array'],
        ['from_node' => 'jn', 'from_socket' => 'value', 'to_node' => 'o',  'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe('a | b | c | d');
});

test('equals compares loose-equal', function () {
    $build = function ($a, $b) {
        return [
            ['id' => 'ca', 'type' => 'source.constant',  'settings' => ['value' => (string) $a]],
            ['id' => 'cb', 'type' => 'source.constant',  'settings' => ['value' => (string) $b]],
            ['id' => 'eq', 'type' => 'transform.equals', 'settings' => []],
            ['id' => 'o',  'type' => 'output',           'settings' => ['name' => 'r']],
        ];
    };
    $edges = [
        ['from_node' => 'ca', 'from_socket' => 'value', 'to_node' => 'eq', 'to_socket' => 'a'],
        ['from_node' => 'cb', 'from_socket' => 'value', 'to_node' => 'eq', 'to_socket' => 'b'],
        ['from_node' => 'eq', 'from_socket' => 'value', 'to_node' => 'o',  'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($build('hi', 'hi'), $edges, [])['r'])->toBeTrue();
    expect(NodeGraphEngine::evaluate($build('hi', 'bye'), $edges, [])['r'])->toBeFalse();
});

test('if/else picks the branch by the condition input', function () {
    $build = function (bool $cond) {
        return [
            ['id' => 'c', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'flag']],
            ['id' => 't', 'type' => 'source.constant',       'settings' => ['value' => 'yes']],
            ['id' => 'e', 'type' => 'source.constant',       'settings' => ['value' => 'no']],
            ['id' => 'if','type' => 'transform.if',          'settings' => []],
            ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'r']],
        ];
    };
    $edges = [
        ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'if', 'to_socket' => 'condition'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'if', 'to_socket' => 'then'],
        ['from_node' => 'e', 'from_socket' => 'value', 'to_node' => 'if', 'to_socket' => 'else'],
        ['from_node' => 'if','from_socket' => 'value', 'to_node' => 'o',  'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($build(true),  $edges, ['flag' => true])['r'])->toBe('yes');
    expect(NodeGraphEngine::evaluate($build(false), $edges, ['flag' => false])['r'])->toBe('no');
});

test('first returns the first item of an array', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'arr']],
        ['id' => 'f', 'type' => 'transform.first',       'settings' => []],
        ['id' => 'o', 'type' => 'output',                'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'f', 'to_socket' => 'array'],
        ['from_node' => 'f', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, ['arr' => [42, 1, 2]])['r'])->toBe(42);
});

test('convert.to_int parses strings to integers', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',  'settings' => ['value' => '42']],
        ['id' => 'c', 'type' => 'convert.to_int',   'settings' => []],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
        ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe(42);
});

test('convert.to_bool falsy strings come out false', function () {
    $for = fn ($input) => NodeGraphEngine::evaluate(
        [
            ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => $input]],
            ['id' => 'c', 'type' => 'convert.to_bool', 'settings' => []],
            ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
        ],
        [
            ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
            ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
        ],
        [],
    )['r'];
    expect($for('false'))->toBeFalse();
    expect($for('0'))->toBeFalse();
    expect($for(''))->toBeFalse();
    expect($for('yes'))->toBeTrue();
});

test('convert.to_array decodes JSON strings into arrays', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',  'settings' => ['value' => '[1,2,3]']],
        ['id' => 'c', 'type' => 'convert.to_array', 'settings' => []],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
        ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe([1, 2, 3]);
});

test('NodeSchema::normaliseSocket accepts both legacy + typed shapes', function () {
    $legacy = \LoggedCloud\PageStudio\Support\NodeSchema::normaliseSocket('Text');
    expect($legacy)->toMatchArray(['label' => 'Text', 'type' => 'any']);

    $typed = \LoggedCloud\PageStudio\Support\NodeSchema::normaliseSocket(['label' => 'X', 'type' => 'int']);
    expect($typed)->toMatchArray(['label' => 'X', 'type' => 'int']);
});

test('math node applies the picked operator', function () {
    $build = fn (string $op) => [
        ['id' => 'a',  'type' => 'source.constant',  'settings' => ['value' => '10']],
        ['id' => 'b',  'type' => 'source.constant',  'settings' => ['value' => '3']],
        ['id' => 'm',  'type' => 'transform.math',   'settings' => ['op' => $op]],
        ['id' => 'o',  'type' => 'output',           'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'm', 'to_socket' => 'a'],
        ['from_node' => 'b', 'from_socket' => 'value', 'to_node' => 'm', 'to_socket' => 'b'],
        ['from_node' => 'm', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($build('+'), $edges, [])['r'])->toBe(13);
    expect(NodeGraphEngine::evaluate($build('-'), $edges, [])['r'])->toBe(7);
    expect(NodeGraphEngine::evaluate($build('*'), $edges, [])['r'])->toBe(30);
    // 10 / 3 is fractional, keep as float
    expect(round(NodeGraphEngine::evaluate($build('/'), $edges, [])['r'], 4))->toBe(3.3333);
});

test('math divide-by-zero returns null', function () {
    $nodes = [
        ['id' => 'a', 'type' => 'source.constant', 'settings' => ['value' => '5']],
        ['id' => 'b', 'type' => 'source.constant', 'settings' => ['value' => '0']],
        ['id' => 'm', 'type' => 'transform.math',  'settings' => ['op' => '/']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'm', 'to_socket' => 'a'],
        ['from_node' => 'b', 'from_socket' => 'value', 'to_node' => 'm', 'to_socket' => 'b'],
        ['from_node' => 'm', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBeNull();
});

test('source.now emits a DateTimeImmutable each evaluation', function () {
    $nodes = [
        ['id' => 'n', 'type' => 'source.now', 'settings' => []],
        ['id' => 'o', 'type' => 'output',     'settings' => ['name' => 't']],
    ];
    $edges = [
        ['from_node' => 'n', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['t'])->toBeInstanceOf(\DateTimeImmutable::class);
});

test('evaluateAll returns nodeOutputs keyed by id then socket', function () {
    $nodes = [
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'hi']],
        ['id' => 't', 'type' => 'transform.uppercase', 'settings' => []],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $result = NodeGraphEngine::evaluateAll($nodes, $edges, []);
    expect($result['nodeOutputs']['s']['value'])->toBe('hi');
    expect($result['nodeOutputs']['t']['value'])->toBe('HI');
    expect($result['context']['r'])->toBe('HI');
});

test('note nodes do not participate in evaluation and produce no outputs', function () {
    $nodes = [
        ['id' => 'n', 'type' => 'note',           'settings' => ['text' => 'just a label']],
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'go']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $result = NodeGraphEngine::evaluateAll($nodes, $edges, []);
    expect($result['context']['r'])->toBe('go');
    expect($result['nodeOutputs']['n'] ?? [])->toBe([]);
});

test('PageBuilder::tidy lays out nodes by dependency depth · sources left, output right', function () {
    $component = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $component->nodes = [
        ['id' => 'a', 'type' => 'source.constant',  'position' => ['x' => 999, 'y' => 999], 'settings' => ['value' => 'hi']],
        ['id' => 'b', 'type' => 'transform.uppercase', 'position' => ['x' => 999, 'y' => 999], 'settings' => []],
        ['id' => 'c', 'type' => 'output',            'position' => ['x' => 999, 'y' => 999], 'settings' => ['name' => 'r']],
    ];
    $component->edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'b', 'to_socket' => 'text'],
        ['from_node' => 'b', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
    ];
    $component->tidy();
    // After tidy: a.x < b.x < c.x
    $by = collect($component->nodes)->keyBy('id');
    expect($by['a']['position']['x'])->toBeLessThan($by['b']['position']['x']);
    expect($by['b']['position']['x'])->toBeLessThan($by['c']['position']['x']);
});

test('tidy parks note nodes off to the leftmost column', function () {
    $component = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $component->nodes = [
        ['id' => 'n', 'type' => 'note',             'position' => ['x' => 999, 'y' => 999], 'settings' => ['text' => 'x']],
        ['id' => 'a', 'type' => 'source.constant',  'position' => ['x' => 999, 'y' => 999], 'settings' => ['value' => 'hi']],
        ['id' => 'c', 'type' => 'output',            'position' => ['x' => 999, 'y' => 999], 'settings' => ['name' => 'r']],
    ];
    $component->edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
    ];
    $component->tidy();
    $by = collect($component->nodes)->keyBy('id');
    // Notes get column -1 + offset so they sit to the LEFT of sources.
    expect($by['n']['position']['x'])->toBeLessThan($by['a']['position']['x']);
});

test('image.source emits an image value with an empty filter', function () {
    $nodes = [
        ['id' => 's', 'type' => 'image.source', 'settings' => ['url' => 'https://x/y.png']],
        ['id' => 'o', 'type' => 'output',       'settings' => ['name' => 'img']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'image', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['img'])->toBe(['url' => 'https://x/y.png', 'filter' => '']);
});

test('image filter nodes compose a CSS filter chain in order', function () {
    $nodes = [
        ['id' => 's', 'type' => 'image.source',    'settings' => ['url' => 'https://x/y.png']],
        ['id' => 'g', 'type' => 'image.grayscale', 'settings' => ['value' => '1']],
        ['id' => 'b', 'type' => 'image.blur',      'settings' => ['value' => '3']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'img']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'image', 'to_node' => 'g', 'to_socket' => 'image'],
        ['from_node' => 'g', 'from_socket' => 'image', 'to_node' => 'b', 'to_socket' => 'image'],
        ['from_node' => 'b', 'from_socket' => 'image', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['img']['url'])->toBe('https://x/y.png');
    expect($ctx['img']['filter'])->toBe('grayscale(1) blur(3px)');
});

test('hue-rotate emits the deg unit', function () {
    $nodes = [
        ['id' => 's', 'type' => 'image.source',     'settings' => ['url' => '/a.png']],
        ['id' => 'h', 'type' => 'image.hue_rotate', 'settings' => ['value' => '180']],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'img']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'image', 'to_node' => 'h', 'to_socket' => 'image'],
        ['from_node' => 'h', 'from_socket' => 'image', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['img']['filter'])->toBe('hue-rotate(180deg)');
});

test('image filter on a null input passes null through (no crash)', function () {
    $nodes = [
        ['id' => 'h', 'type' => 'image.hue_rotate', 'settings' => ['value' => '90']],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'img']],
    ];
    $edges = [
        ['from_node' => 'h', 'from_socket' => 'image', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['img'])->toBeNull();
});

test('muted node passes its first input straight through to every output', function () {
    // uppercase node muted · should NOT change the case.
    $nodes = [
        ['id' => 's', 'type' => 'source.constant',      'settings' => ['value' => 'hi']],
        ['id' => 't', 'type' => 'transform.uppercase',  'settings' => [], 'muted' => true],
        ['id' => 'o', 'type' => 'output',               'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 't', 'to_socket' => 'text'],
        ['from_node' => 't', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe('hi');
});

test('toggleMuted flips the flag on the right node', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [
        ['id' => 'a', 'type' => 'transform.uppercase', 'settings' => [], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'b', 'type' => 'transform.uppercase', 'settings' => [], 'position' => ['x' => 0, 'y' => 0]],
    ];
    $c->toggleMuted('b');
    expect($c->nodes[0])->not->toHaveKey('muted');
    expect($c->nodes[1]['muted'])->toBeTrue();
    $c->toggleMuted('b');
    expect($c->nodes[1]['muted'])->toBeFalse();
});

test('duplicateNode appends a copy with a new id, offset position, and selects it', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [
        ['id' => 'a', 'type' => 'transform.uppercase', 'settings' => [], 'position' => ['x' => 100, 'y' => 50]],
    ];
    $c->duplicateNode('a');
    expect($c->nodes)->toHaveCount(2);
    expect($c->nodes[1]['id'])->not->toBe('a');
    expect($c->nodes[1]['type'])->toBe('transform.uppercase');
    expect($c->nodes[1]['position'])->toBe(['x' => 130, 'y' => 80]);
    expect($c->selectedNodeId)->toBe($c->nodes[1]['id']);
});

test('undo restores the previous graph state, redo replays it', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [];
    $c->edges = [];

    $c->addNode('source.constant');
    expect($c->nodes)->toHaveCount(1);
    $afterAdd = $c->nodes[0]['id'];

    $c->undo();
    expect($c->nodes)->toHaveCount(0);

    $c->redo();
    expect($c->nodes)->toHaveCount(1);
    expect($c->nodes[0]['id'])->toBe($afterAdd);
});

test('mutating after an undo clears the redo stack · classic branch invalidation', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [];
    $c->edges = [];

    $c->addNode('source.constant');
    $c->undo();           // now nodes=[]
    expect($c->redoStack)->not->toBeEmpty();

    $c->addNode('output'); // new branch · redo is no longer reachable
    expect($c->redoStack)->toBeEmpty();
});

test('removeNodes wipes the selected ids and their edges in one history step', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [
        ['id' => 'a', 'type' => 'source.constant',    'settings' => ['value' => 'hi'], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'b', 'type' => 'transform.uppercase', 'settings' => [],               'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'c', 'type' => 'output',             'settings' => ['name' => 'r'],  'position' => ['x' => 0, 'y' => 0]],
    ];
    $c->edges = [
        ['from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'b', 'to_socket' => 'text'],
        ['from_node' => 'b', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'value'],
    ];
    $c->removeNodes(['a', 'b']);
    expect($c->nodes)->toHaveCount(1);
    expect($c->nodes[0]['id'])->toBe('c');
    expect($c->edges)->toHaveCount(0);
    // One history snapshot, not two.
    expect($c->undoStack)->toHaveCount(1);
});

test('image variable in the page src setting renders the URL + filter chain', function () {
    $html = \LoggedCloud\PageStudio\Support\PageRenderer::renderBlock(
        ['type' => 'image', 'settings' => ['src' => '{{ avatar }}', 'alt' => 'Avatar']],
        ['avatar' => ['url' => 'https://x/y.png', 'filter' => 'grayscale(1) blur(2px)']],
    );
    expect($html)->toContain('src="https://x/y.png"')
        ->and($html)->toContain('filter:grayscale(1) blur(2px)')
        ->and($html)->toContain('alt="Avatar"');
});

test('image variable falls back to a plain URL when not an image array', function () {
    // String value should be treated as a plain URL.
    $html = \LoggedCloud\PageStudio\Support\PageRenderer::renderBlock(
        ['type' => 'image', 'settings' => ['src' => '{{ url }}', 'alt' => '']],
        ['url' => 'https://x/plain.png'],
    );
    expect($html)->toContain('src="https://x/plain.png"')
        ->and($html)->not->toContain('filter:');
});

test('pasteSubgraph clones nodes with new ids and remaps internal edges', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [];
    $c->edges = [];

    $clip = [
        ['id' => 'old-a', 'type' => 'source.constant',     'settings' => ['value' => 'hi'], 'position' => ['x' => 10, 'y' => 10]],
        ['id' => 'old-b', 'type' => 'transform.uppercase', 'settings' => [],               'position' => ['x' => 200, 'y' => 10]],
    ];
    $edges = [
        ['id' => 'old-e', 'from_node' => 'old-a', 'from_socket' => 'value', 'to_node' => 'old-b', 'to_socket' => 'text'],
    ];
    $c->pasteSubgraph($clip, $edges, 50, 60);

    expect($c->nodes)->toHaveCount(2);
    expect($c->edges)->toHaveCount(1);
    // Ids are rewritten so a re-paste doesn't collide.
    expect($c->nodes[0]['id'])->not->toBe('old-a');
    // Positions are shifted by (dx, dy).
    expect($c->nodes[0]['position'])->toBe(['x' => 60, 'y' => 70]);
    // Edge endpoints map onto the new ids.
    expect($c->edges[0]['from_node'])->toBe($c->nodes[0]['id']);
    expect($c->edges[0]['to_node'])  ->toBe($c->nodes[1]['id']);
    // Newest node selected.
    expect($c->selectedNodeId)->toBe($c->nodes[0]['id']);
});

test('pasteSubgraph drops edges that reach outside the copied set', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->nodes = [];
    $c->edges = [];

    $clip  = [['id' => 'x', 'type' => 'source.constant', 'settings' => [], 'position' => ['x' => 0, 'y' => 0]]];
    $edges = [['from_node' => 'x', 'from_socket' => 'value', 'to_node' => 'NOT-COPIED', 'to_socket' => 'value']];

    $c->pasteSubgraph($clip, $edges, 0, 0);
    expect($c->nodes)->toHaveCount(1);
    expect($c->edges)->toHaveCount(0);
});

test('bendEdge stores or clears a manual reroute point on the edge', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->edges = [
        ['id' => 'e1', 'from_node' => 'a', 'from_socket' => 'value', 'to_node' => 'b', 'to_socket' => 'value'],
    ];

    $c->bendEdge('e1', 120, 80);
    expect($c->edges[0]['bend'])->toBe(['x' => 120, 'y' => 80]);

    $c->bendEdge('e1', null, null);
    expect($c->edges[0])->not->toHaveKey('bend');
});

test('custom-node template substitutes inputs + settings into the output', function () {
    // Register a fake custom node in the config so the engine sees it.
    $lib = config('page-studio.nodes', []);
    $lib['custom.greet'] = [
        'group'    => 'transform',
        'label'    => 'Greeting',
        'icon'     => '✦',
        'inputs'   => ['name' => ['label' => 'Name', 'type' => 'string']],
        'outputs'  => ['value' => ['label' => 'Greeting', 'type' => 'string']],
        'settings' => ['greeting' => ['kind' => 'text', 'label' => 'Greeting', 'default' => 'Hello']],
        'custom'   => true,
        'template' => '{{ settings.greeting }}, {{ inputs.name }}!',
    ];
    config(['page-studio.nodes' => $lib]);

    $nodes = [
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'Charles']],
        ['id' => 'c', 'type' => 'custom.greet',    'settings' => ['greeting' => 'Howdy']],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'name'],
        ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    expect(NodeGraphEngine::evaluate($nodes, $edges, [])['r'])->toBe('Howdy, Charles!');
});

test('custom-node template accepts the {{ name }} shorthand and resolves inputs first', function () {
    $lib = config('page-studio.nodes', []);
    $lib['custom.shorty'] = [
        'group'    => 'transform',
        'label'    => 'Shorty',
        'icon'     => '✦',
        'inputs'   => ['name' => ['label' => 'Name', 'type' => 'string']],
        'outputs'  => ['value' => ['label' => 'Out', 'type' => 'string']],
        'settings' => ['name' => ['kind' => 'text', 'label' => 'Name', 'default' => 'fallback']],
        'custom'   => true,
        'template' => 'Hi {{ name }}!',
    ];
    config(['page-studio.nodes' => $lib]);

    $build = function ($withWire) {
        $base = [
            ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'wired']],
            ['id' => 'c', 'type' => 'custom.shorty',   'settings' => ['name' => 'fallback']],
            ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
        ];
        $edges = [
            ['from_node' => 'c', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
        ];
        if ($withWire) {
            $edges[] = ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'c', 'to_socket' => 'name'];
        }
        return NodeGraphEngine::evaluate($base, $edges, []);
    };

    // With the input wired, shorthand should resolve to the input value.
    expect($build(true)['r'])->toBe('Hi wired!');
    // Without the wire, the shorthand falls back to the same-named setting.
    expect($build(false)['r'])->toBe('Hi fallback!');
});

test('unknown node types with no config entry produce no outputs', function () {
    $nodes = [['id' => 'x', 'type' => 'custom.nonexistent', 'settings' => []]];
    expect(NodeGraphEngine::evaluateAll($nodes, [], [])['nodeOutputs']['x'])->toBe([]);
});

test('engine result cache returns the same array for identical inputs in one request', function () {
    \LoggedCloud\PageStudio\Support\NodeGraphEngine::flushCache();
    $nodes = [
        ['id' => 's', 'type' => 'source.constant', 'settings' => ['value' => 'hi']],
        ['id' => 'o', 'type' => 'output',          'settings' => ['name' => 'r']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    $a = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluateAll($nodes, $edges, []);
    $b = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluateAll($nodes, $edges, []);
    expect($a)->toBe($b);
    expect($a['context']['r'])->toBe('hi');
});

test('snapshotting a revision captures blocks + nodes + edges + author', function () {
    $component = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $component->routeId = 1;
    $component->blocks = [['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'Hi']]];
    $component->nodes  = [['id' => 'n', 'type' => 'source.constant', 'settings' => ['value' => 'x']]];
    $component->edges  = [];

    // Reflection because snapshotRevision is protected.
    $r = new \ReflectionMethod($component, 'snapshotRevision');
    $r->setAccessible(true);
    $r->invoke($component);

    $row = \LoggedCloud\PageStudio\Models\Revision::where('route_id', 1)->latest('id')->first();
    expect($row)->not->toBeNull();
    expect(count($row->blocks))->toBe(1);
    expect(count($row->nodes))->toBe(1);
});

test('events fire on save', function () {
    \Illuminate\Support\Facades\Event::fake([
        \LoggedCloud\PageStudio\Events\PageSaved::class,
        \LoggedCloud\PageStudio\Events\GraphSaved::class,
    ]);
    $component = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $component->routeId = 1;
    $component->blocks = [['id' => 'h', 'type' => 'heading', 'settings' => ['text' => 'X', 'level' => 'h2', 'align' => 'left']]];
    $component->nodes  = [];
    $component->edges  = [];
    $component->save();
    \Illuminate\Support\Facades\Event::assertDispatched(\LoggedCloud\PageStudio\Events\PageSaved::class);

    $component->nodes = [['id' => 'n', 'type' => 'source.constant', 'settings' => ['value' => 'a']]];
    $component->saveGraph();
    \Illuminate\Support\Facades\Event::assertDispatched(\LoggedCloud\PageStudio\Events\GraphSaved::class);
});

test('AuthorizesPageStudio is a no-op when no gate is configured', function () {
    config(['page-studio.gate' => null]);
    $obj = new class {
        use \LoggedCloud\PageStudio\Concerns\AuthorizesPageStudio;
        public function call() { $this->authorizePageStudio(); }
    };
    // Should not throw.
    $obj->call();
    expect(true)->toBeTrue();
});

test('diffRevision reports the delta between the current state and a snapshot', function () {
    $c = new \LoggedCloud\PageStudio\Livewire\PageBuilder();
    $c->routeId = 1;
    $c->blocks  = [['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'a']]];
    $c->nodes   = [];
    $c->edges   = [];

    // Snapshot the current state, then add more.
    $r = new \ReflectionMethod($c, 'snapshotRevision');
    $r->setAccessible(true);
    $r->invoke($c);

    $revId = \LoggedCloud\PageStudio\Models\Revision::where('route_id', 1)->latest('id')->value('id');

    $c->blocks[] = ['id' => 'b', 'type' => 'paragraph', 'settings' => ['text' => 'b']];
    $c->nodes[] = ['id' => 'n', 'type' => 'source.constant', 'settings' => ['value' => 'x']];

    expect($c->diffRevision($revId))->toBe([
        'blocks' => 1,
        'nodes'  => 1,
        'edges'  => 0,
    ]);
});
