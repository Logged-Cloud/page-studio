<?php

use LoggedCloud\PageStudio\Nodes\Builtin\TransformFormatDateNode;
use LoggedCloud\PageStudio\Support\NodeHelpers;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('returns the formatted date unchanged when offset is 0', function () {
    $node = new TransformFormatDateNode();
    $out  = $node->evaluate(['value' => '2026-05-25 12:00:00'], ['format' => 'Y-m-d'], []);
    expect($out['value'])->toBe('2026-05-25');
});

it('adds positive days', function () {
    $node = new TransformFormatDateNode();
    $out  = $node->evaluate(
        ['value' => '2026-05-25 12:00:00'],
        ['format' => 'Y-m-d', 'offset_amount' => 7, 'offset_unit' => 'days'],
        [],
    );
    expect($out['value'])->toBe('2026-06-01');
});

it('subtracts when amount is negative', function () {
    $node = new TransformFormatDateNode();
    $out  = $node->evaluate(
        ['value' => '2026-05-25'],
        ['format' => 'Y-m-d', 'offset_amount' => -3, 'offset_unit' => 'days'],
        [],
    );
    expect($out['value'])->toBe('2026-05-22');
});

it('honours other units · hours, weeks, months, years', function () {
    $node = new TransformFormatDateNode();

    expect($node->evaluate(['value' => '2026-05-25 12:00:00'], ['format' => 'Y-m-d H:i', 'offset_amount' => 3, 'offset_unit' => 'hours'], [])['value'])
        ->toBe('2026-05-25 15:00');

    expect($node->evaluate(['value' => '2026-05-25'], ['format' => 'Y-m-d', 'offset_amount' => 2, 'offset_unit' => 'weeks'], [])['value'])
        ->toBe('2026-06-08');

    expect($node->evaluate(['value' => '2026-05-25'], ['format' => 'Y-m-d', 'offset_amount' => 1, 'offset_unit' => 'months'], [])['value'])
        ->toBe('2026-06-25');

    expect($node->evaluate(['value' => '2026-05-25'], ['format' => 'Y-m-d', 'offset_amount' => -1, 'offset_unit' => 'years'], [])['value'])
        ->toBe('2025-05-25');
});

it('supports the "iso" and "timestamp" format aliases', function () {
    expect(NodeHelpers::formatDate('2026-05-25T12:00:00+00:00', 'iso'))
        ->toMatch('/^2026-05-25T12:00:00/');
    expect(NodeHelpers::formatDate('2026-05-25T00:00:00+00:00', 'timestamp'))
        ->toBe((string) strtotime('2026-05-25T00:00:00+00:00'));
});

it('falls back to null on unparseable input', function () {
    $node = new TransformFormatDateNode();
    expect($node->evaluate(['value' => 'not a date'], ['format' => 'Y-m-d'], [])['value'])->toBeNull();
});
