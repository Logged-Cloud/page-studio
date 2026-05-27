<?php

use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Procedural geometry nodes · Blender-style generators that emit
 * SVG data URIs which the rest of the image pipeline can filter,
 * tint, hue-rotate, etc. Every parameter is wireable (the engine's
 * settings-as-implicit-inputs merge lets a Constant or Math result
 * drive any setting).
 */

it('image.gradient renders a two-stop linear gradient SVG carrying both stop colors', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageGradientNode();
    $out  = $node->evaluate([], [
        'from'   => '#ff0000',
        'to'     => '#0000ff',
        'angle'  => 90,
        'width'  => 400,
        'height' => 200,
    ], []);

    expect($out)->toHaveKey('image')
        ->and($out['image']['url'])->toContain('data:image/svg+xml')
        ->and(rawurldecode($out['image']['url']))->toContain('#ff0000')
        ->and(rawurldecode($out['image']['url']))->toContain('#0000ff')
        ->and(rawurldecode($out['image']['url']))->toContain('linearGradient');
});

it('image.gradient pipes its `from` color through the engine as a settings-socket', function () {
    $nodes = [
        ['id' => 'col', 'type' => 'source.color',    'settings' => ['color' => '#abcdef']],
        ['id' => 'gr',  'type' => 'image.gradient',  'settings' => ['from' => '#000000', 'to' => '#ffffff', 'angle' => 90, 'width' => 200, 'height' => 200]],
        ['id' => 'out', 'type' => 'output',          'settings' => ['name' => 'g']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'col', 'from_socket' => 'color', 'to_node' => 'gr',  'to_socket' => 'from'],
        ['id' => 'e2', 'from_node' => 'gr',  'from_socket' => 'image', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect(rawurldecode($ctx['g']['url']))->toContain('#abcdef');
});

it('image.stripes renders an alternating two-color stripe pattern', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageStripesNode();
    $out  = $node->evaluate([], [
        'a'        => '#111111',
        'b'        => '#eeeeee',
        'width'    => 40,
        'angle'    => 45,
        'imgWidth' => 200,
        'imgHeight'=> 200,
    ], []);

    expect($out['image']['url'])->toContain('data:image/svg+xml')
        ->and(rawurldecode($out['image']['url']))->toContain('#111111')
        ->and(rawurldecode($out['image']['url']))->toContain('#eeeeee')
        ->and(rawurldecode($out['image']['url']))->toContain('rotate(45');
});

it('image.checkerboard renders a 2-cell checkerboard pattern', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageCheckerboardNode();
    $out  = $node->evaluate([], [
        'a'        => '#222222',
        'b'        => '#ffffff',
        'cell'     => 20,
        'imgWidth' => 200,
        'imgHeight'=> 200,
    ], []);

    expect($out['image']['url'])->toContain('data:image/svg+xml');
    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('#222222')
        ->and($decoded)->toContain('#ffffff')
        ->and($decoded)->toContain('pattern');
});

it('image.noise renders an SVG turbulence filter image', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageNoiseNode();
    $out  = $node->evaluate([], [
        'seed'     => 7,
        'scale'    => 0.6,
        'imgWidth' => 300,
        'imgHeight'=> 120,
    ], []);

    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('feTurbulence')
        ->and($decoded)->toContain('seed="7"');
});

it('image.radial_gradient renders a centred two-stop radial SVG', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageRadialGradientNode();
    $out  = $node->evaluate([], [
        'from'   => '#ffffff',
        'to'     => '#000000',
        'width'  => 200,
        'height' => 200,
    ], []);

    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('radialGradient')
        ->and($decoded)->toContain('#ffffff')
        ->and($decoded)->toContain('#000000');
});

it('image.brick renders an offset-row brick pattern with mortar between rows', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageBrickNode();
    $out  = $node->evaluate([], [
        'brick'      => '#8B4513',
        'mortar'     => '#222222',
        'brickWidth' => 60,
        'brickHeight'=> 30,
        'mortarSize' => 2,
        'imgWidth'   => 300,
        'imgHeight'  => 200,
    ], []);

    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('#8B4513')
        ->and($decoded)->toContain('#222222')
        ->and($decoded)->toContain('pattern');
});

it('image.dots renders a grid of evenly-spaced circles', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageDotsNode();
    $out  = $node->evaluate([], [
        'dot'        => '#ffffff',
        'background' => '#0E1116',
        'radius'     => 4,
        'spacing'    => 24,
        'imgWidth'   => 200,
        'imgHeight'  => 200,
    ], []);

    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('#ffffff')
        ->and($decoded)->toContain('#0E1116')
        ->and($decoded)->toContain('<circle');
});

it('image.wave renders SVG sine-wave bands at the requested frequency', function () {
    $node = new \LoggedCloud\PageStudio\Nodes\Builtin\ImageWaveNode();
    $out  = $node->evaluate([], [
        'a'         => '#FF00FF',
        'b'         => '#000000',
        'frequency' => 4,
        'amplitude' => 20,
        'imgWidth'  => 400,
        'imgHeight' => 200,
    ], []);

    $decoded = rawurldecode($out['image']['url']);
    expect($decoded)->toContain('#FF00FF')
        ->and($decoded)->toContain('#000000')
        ->and($decoded)->toMatch('/<path|<rect/');
});

it('a procedural image still composes through image.hue_rotate · the whole pipeline stays consistent', function () {
    $nodes = [
        ['id' => 'gr',  'type' => 'image.gradient',  'settings' => ['from' => '#ff0000', 'to' => '#00ff00', 'angle' => 0, 'width' => 200, 'height' => 200]],
        ['id' => 'rot', 'type' => 'image.hue_rotate','settings' => ['value' => 180]],
        ['id' => 'out', 'type' => 'output',          'settings' => ['name' => 'flipped']],
    ];
    $edges = [
        ['id' => 'e1', 'from_node' => 'gr',  'from_socket' => 'image', 'to_node' => 'rot', 'to_socket' => 'image'],
        ['id' => 'e2', 'from_node' => 'rot', 'from_socket' => 'image', 'to_node' => 'out', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);

    expect($ctx['flipped']['filter'])->toBe('hue-rotate(180deg)')
        ->and($ctx['flipped']['url'])->toContain('data:image/svg+xml');
});
