<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LoggedCloud\PageStudio\Nodes\Builtin\SourceHttpFetchNode;
use LoggedCloud\PageStudio\Nodes\Builtin\TransformCurrencyFormatNode;
use LoggedCloud\PageStudio\Nodes\Builtin\TransformLoopMapNode;
use LoggedCloud\PageStudio\Nodes\Builtin\TransformNumberFormatNode;
use LoggedCloud\PageStudio\Nodes\Builtin\TransformSwitchCaseNode;
use LoggedCloud\PageStudio\Nodes\NodeRegistry;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    Cache::flush();
});

test('SourceHttpFetchNode returns null body when url is empty', function () {
    $node = new SourceHttpFetchNode();
    $out  = $node->evaluate(['url' => ''], ['method' => 'GET', 'ttl' => 0], []);
    expect($out)->toBe(['body' => null, 'json' => null, 'status' => 0]);
});

test('SourceHttpFetchNode parses a JSON 200 response into the json socket', function () {
    Http::fake([
        '*' => Http::response(['hello' => 'world'], 200, ['Content-Type' => 'application/json']),
    ]);

    $node = new SourceHttpFetchNode();
    $out  = $node->evaluate(
        ['url' => 'https://example.test/api'],
        ['method' => 'GET', 'ttl' => 0, 'header_accept' => 'application/json'],
        [],
    );

    expect($out['status'])->toBe(200)
        ->and($out['json'])->toBe(['hello' => 'world'])
        ->and($out['body'])->toContain('hello');
});

test('SourceHttpFetchNode caches the response when ttl is positive', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['n' => 1], 200, ['Content-Type' => 'application/json'])
            ->push(['n' => 2], 200, ['Content-Type' => 'application/json']),
    ]);

    $node = new SourceHttpFetchNode();
    $first  = $node->evaluate(['url' => 'https://example.test/cached'], ['method' => 'GET', 'ttl' => 60, 'header_accept' => 'application/json'], []);
    $second = $node->evaluate(['url' => 'https://example.test/cached'], ['method' => 'GET', 'ttl' => 60, 'header_accept' => 'application/json'], []);

    expect($first['json'])->toBe(['n' => 1])
        ->and($second['json'])->toBe(['n' => 1]); // cached, not the next sequence value
});

test('SourceHttpFetchNode bypasses cache when ttl is zero', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push(['n' => 1], 200, ['Content-Type' => 'application/json'])
            ->push(['n' => 2], 200, ['Content-Type' => 'application/json']),
    ]);

    $node = new SourceHttpFetchNode();
    $first  = $node->evaluate(['url' => 'https://example.test/fresh'], ['method' => 'GET', 'ttl' => 0, 'header_accept' => 'application/json'], []);
    $second = $node->evaluate(['url' => 'https://example.test/fresh'], ['method' => 'GET', 'ttl' => 0, 'header_accept' => 'application/json'], []);

    expect($first['json'])->toBe(['n' => 1])
        ->and($second['json'])->toBe(['n' => 2]);
});

test('TransformLoopMapNode maps array of associative items with {{ item.key }}', function () {
    $node = new TransformLoopMapNode();
    $out  = $node->evaluate(
        ['array' => [['name' => 'A'], ['name' => 'B']], 'template' => 'Hello, {{ item.name }}!'],
        [],
        [],
    );
    expect($out['value'])->toBe(['Hello, A!', 'Hello, B!']);
});

test('TransformLoopMapNode honours the {{ index }} token', function () {
    $node = new TransformLoopMapNode();
    $out  = $node->evaluate(
        ['array' => ['a', 'b', 'c'], 'template' => '{{ index }}: {{ item }}'],
        [],
        [],
    );
    expect($out['value'])->toBe(['0: a', '1: b', '2: c']);
});

test('TransformLoopMapNode returns [] when input is null or scalar', function () {
    $node = new TransformLoopMapNode();
    expect($node->evaluate(['array' => null, 'template' => 'x'], [], [])['value'])->toBe([]);
    // A scalar gets wrapped to a one-item array by NodeHelpers::toArray, but
    // an empty string short-circuits to [].
    expect($node->evaluate(['array' => '', 'template' => 'x'], [], [])['value'])->toBe([]);
});

test('TransformSwitchCaseNode picks the matching case', function () {
    $node = new TransformSwitchCaseNode();
    $out  = $node->evaluate(
        ['value' => 'red', 'default' => 'other'],
        ['cases' => "red|stop\ngreen|go"],
        [],
    );
    expect($out['value'])->toBe('stop');
});

test('TransformSwitchCaseNode falls back to default input on no match', function () {
    $node = new TransformSwitchCaseNode();
    $out  = $node->evaluate(
        ['value' => 'blue', 'default' => 'other'],
        ['cases' => "red|stop\ngreen|go"],
        [],
    );
    expect($out['value'])->toBe('other');
});

test('TransformCurrencyFormatNode formats GBP en_GB with intl when available', function () {
    if (! class_exists(\NumberFormatter::class)) {
        $this->markTestSkipped('intl extension not loaded');
    }
    $node = new TransformCurrencyFormatNode();
    $out  = $node->evaluate(
        ['value' => 1234.5],
        ['currency' => 'GBP', 'locale' => 'en_GB', 'decimals' => 2],
        [],
    );
    // NumberFormatter on en_GB renders GBP as "£1,234.50".
    expect($out['value'])->toBe('£1,234.50');
});

test('TransformCurrencyFormatNode falls back to plain number_format when intl missing', function () {
    if (class_exists(\NumberFormatter::class)) {
        $this->markTestSkipped('intl extension is loaded · fallback path not exercised');
    }
    $node = new TransformCurrencyFormatNode();
    $out  = $node->evaluate(
        ['value' => 1234.5],
        ['currency' => 'USD', 'locale' => 'en_US', 'decimals' => 2],
        [],
    );
    expect($out['value'])->toBe('1,234.50 USD');
});

test('TransformNumberFormatNode honours decimals + thousands/decimal separators', function () {
    $node = new TransformNumberFormatNode();
    $out  = $node->evaluate(
        ['value' => 1234567.89],
        ['decimals' => 2, 'thousands_separator' => ' ', 'decimal_separator' => ','],
        [],
    );
    expect($out['value'])->toBe('1 234 567,89');
});

test('TransformNumberFormatNode defaults to zero decimals + comma thousands', function () {
    $node = new TransformNumberFormatNode();
    $out  = $node->evaluate(['value' => 1234567], [], []);
    expect($out['value'])->toBe('1,234,567');
});

test('all five new nodes register in NodeRegistry', function () {
    expect(NodeRegistry::find('source.http_fetch'))->toBe(SourceHttpFetchNode::class)
        ->and(NodeRegistry::find('transform.loop_map'))->toBe(TransformLoopMapNode::class)
        ->and(NodeRegistry::find('transform.switch_case'))->toBe(TransformSwitchCaseNode::class)
        ->and(NodeRegistry::find('transform.currency_format'))->toBe(TransformCurrencyFormatNode::class)
        ->and(NodeRegistry::find('transform.number_format'))->toBe(TransformNumberFormatNode::class);
});
