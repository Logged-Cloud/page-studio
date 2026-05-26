<?php

use LoggedCloud\PageStudio\Nodes\Builtin\ImageBrightnessNode;
use LoggedCloud\PageStudio\Nodes\Builtin\ImageHueRotateNode;
use LoggedCloud\PageStudio\Nodes\Builtin\ImageSolidNode;
use LoggedCloud\PageStudio\Nodes\Builtin\SourceColorNode;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Blender-style nodes · sources for color + solid-color images, plus
 * the existing CSS-filter nodes can now take their parameter as an
 * INPUT in addition to (or instead of) the static setting. Authors
 * can plumb a Math result or another Constant straight into the
 * brightness / hue-rotate / etc. without leaving the graph.
 */

it('source.color outputs a CSS color string from its setting', function () {
    $node = new SourceColorNode();
    $out  = $node->evaluate([], ['color' => '#ff6b35'], []);

    expect($out)->toBe(['color' => '#ff6b35']);
});

it('image.solid renders a solid-color image from a piped color', function () {
    $node = new ImageSolidNode();
    $out  = $node->evaluate(['color' => '#0f5fa6'], ['width' => 800, 'height' => 240], []);

    expect($out)->toHaveKey('image')
        ->and($out['image']['url'])->toContain('data:image/svg+xml')
        ->and($out['image']['url'])->toContain('%230f5fa6')  // URL-encoded #
        ->and($out['image']['filter'])->toBe('');
});

it('image.solid falls back to its color setting when no input is piped', function () {
    $node = new ImageSolidNode();
    $out  = $node->evaluate([], ['color' => '#abcdef', 'width' => 100, 'height' => 100], []);

    expect($out['image']['url'])->toContain('%23abcdef');
});

it('image.brightness uses the piped `value` input over the static setting', function () {
    $node = new ImageBrightnessNode();
    $img  = ['url' => 'https://x/y.png', 'filter' => ''];
    $out  = $node->evaluate(['image' => $img, 'value' => 1.5], ['value' => '1.0'], []);

    expect($out['image']['filter'])->toBe('brightness(1.5)');
});

it('image.brightness falls back to the static setting when nothing is wired', function () {
    $node = new ImageBrightnessNode();
    $img  = ['url' => 'https://x/y.png', 'filter' => ''];
    $out  = $node->evaluate(['image' => $img], ['value' => '1.25'], []);

    expect($out['image']['filter'])->toBe('brightness(1.25)');
});

it('image.hue_rotate accepts its degrees parameter as an input wire from a constant', function () {
    // End-to-end · constant 180 -> hue rotate -> output. Proves the
    // wire actually flows through the engine, not just that the node
    // reads its own inputs.
    $nodes = [
        ['id' => 'src', 'type' => 'image.source',     'settings' => ['url' => 'https://x/y.png']],
        ['id' => 'deg', 'type' => 'source.constant',  'settings' => ['value' => '180']],
        ['id' => 'hue', 'type' => 'image.hue_rotate', 'settings' => ['value' => '0']],
        ['id' => 'out', 'type' => 'output',           'settings' => ['name' => 'rotated']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'src', 'from_socket' => 'image', 'to_node' => 'hue', 'to_socket' => 'image'],
        ['id' => 'e2', 'from_node' => 'deg', 'from_socket' => 'value', 'to_node' => 'hue', 'to_socket' => 'value'],
        ['id' => 'e3', 'from_node' => 'hue', 'from_socket' => 'image', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['rotated']['filter'])->toBe('hue-rotate(180deg)');
});

it('source.color piped into image.solid into image.hue_rotate composes end-to-end', function () {
    // Three new pieces working together · pick a color, build a solid
    // image from it, then rotate the hue · proves the new sources and
    // input-aware filters all line up in the engine.
    $nodes = [
        ['id' => 'col', 'type' => 'source.color',     'settings' => ['color' => '#ff0000']],
        ['id' => 'img', 'type' => 'image.solid',      'settings' => ['width' => 200, 'height' => 200, 'color' => '#000000']],
        ['id' => 'rot', 'type' => 'image.hue_rotate', 'settings' => ['value' => '90']],
        ['id' => 'out', 'type' => 'output',           'settings' => ['name' => 'mark']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'col', 'from_socket' => 'color', 'to_node' => 'img', 'to_socket' => 'color'],
        ['id' => 'e2', 'from_node' => 'img', 'from_socket' => 'image', 'to_node' => 'rot', 'to_socket' => 'image'],
        ['id' => 'e3', 'from_node' => 'rot', 'from_socket' => 'image', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['mark']['url'])->toContain('%23ff0000')
        ->and($ctx['mark']['filter'])->toBe('hue-rotate(90deg)');
});
