<?php

use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

test('substitute replaces a {{ name }} token with the context value', function () {
    $out = PageRenderer::substitute('Hello {{ name }}!', ['name' => 'Charles']);
    expect($out)->toBe('Hello Charles!');
});

test('substitute is whitespace-tolerant', function () {
    foreach (['{{name}}', '{{ name }}', '{{   name   }}'] as $token) {
        expect(PageRenderer::substitute("X $token Y", ['name' => 'Z']))->toBe('X Z Y');
    }
});

test('substitute leaves unknown variables as the literal token', function () {
    $out = PageRenderer::substitute('Hi {{ missing }}', []);
    expect($out)->toBe('Hi {{ missing }}');
});

test('heading block renders the right tag and escapes content', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'heading',
        'settings' => ['text' => 'Hello <b>world</b>', 'level' => 'h1', 'align' => 'center'],
    ]);
    expect($html)->toContain('<h1')
        ->and($html)->toContain('text-align:center')
        ->and($html)->toContain('Hello &lt;b&gt;world&lt;/b&gt;');
});

test('heading rejects an invalid level and falls back to h2', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'heading',
        'settings' => ['text' => 'X', 'level' => '<script>', 'align' => 'left'],
    ]);
    expect($html)->toContain('<h2')
        ->and($html)->not->toContain('script');
});

test('paragraph renders newlines as <br>', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'paragraph',
        'settings' => ['text' => "Line one\nLine two"],
    ]);
    expect($html)->toContain('Line one<br')
        ->and($html)->toContain('Line two');
});

test('button substitutes variables in href + label', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'button',
        'settings' => ['label' => 'Open {{ slug }}', 'href' => '/posts/{{ slug }}', 'variant' => 'primary'],
    ], ['slug' => 'hello-world']);

    expect($html)->toContain('href="/posts/hello-world"')
        ->and($html)->toContain('Open hello-world');
});

test('image src and alt are escaped + substituted', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'image',
        'settings' => ['src' => 'https://x/{{ id }}.jpg', 'alt' => 'pic'],
    ], ['id' => 7]);
    expect($html)->toContain('src="https://x/7.jpg"')
        ->and($html)->toContain('alt="pic"');
});

test('unknown block types render as empty string · safe by default', function () {
    expect(PageRenderer::renderBlock(['type' => 'made-up']))->toBe('');
});

test('render concatenates a block list in order', function () {
    $blocks = [
        ['type' => 'heading',  'settings' => ['text' => 'A', 'level' => 'h1', 'align' => 'left']],
        ['type' => 'paragraph', 'settings' => ['text' => 'B']],
    ];
    $html = PageRenderer::render($blocks);
    $aPos = strpos($html, 'A');
    $bPos = strpos($html, 'B');
    expect($aPos)->toBeLessThan($bPos);
});

test('decorate=true wraps substituted values in <mark class="ps-var">', function () {
    $html = PageRenderer::renderBlock(
        ['type' => 'paragraph', 'settings' => ['text' => 'User {{ id }}']],
        ['id' => '42'],
        true,
    );
    expect($html)->toContain('<mark class="ps-var"')
        ->and($html)->toContain('data-var="id"')
        ->and($html)->toContain('>42</mark>');
});

test('decorate=true does NOT inject <mark> into href/src attributes', function () {
    $html = PageRenderer::renderBlock(
        ['type' => 'image', 'settings' => ['src' => '/i/{{ id }}.jpg', 'alt' => '']],
        ['id' => '5'],
        true,
    );
    expect($html)->toContain('src="/i/5.jpg"')
        ->and($html)->not->toContain('<mark');
});

test('decorate=false renders bare substituted values (default)', function () {
    $html = PageRenderer::renderBlock(
        ['type' => 'paragraph', 'settings' => ['text' => 'User {{ id }}']],
        ['id' => '42'],
    );
    expect($html)->not->toContain('<mark')
        ->and($html)->toContain('User 42');
});

test('XSS in a substituted value stays escaped under decorate', function () {
    $html = PageRenderer::renderBlock(
        ['type' => 'paragraph', 'settings' => ['text' => 'Hi {{ name }}']],
        ['name' => '<script>alert(1)</script>'],
        true,
    );
    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

test('spacer renders a div with the configured height', function () {
    $html = PageRenderer::renderBlock(['type' => 'spacer', 'settings' => ['size' => 'lg']]);
    expect($html)->toContain('height:3rem');
});

test('quote escapes content and renders the cite footer when present', function () {
    $html = PageRenderer::renderBlock([
        'type' => 'quote',
        'settings' => ['text' => 'They said <stuff>', 'cite' => 'Some One'],
    ]);
    expect($html)->toContain('<blockquote')
        ->and($html)->toContain('They said &lt;stuff&gt;')
        ->and($html)->toContain('Some One');
});

test('list splits items on newlines and supports bullet + number', function () {
    $bullet = PageRenderer::renderBlock([
        'type' => 'list',
        'settings' => ['items' => "Apple\nBanana\nCherry", 'style' => 'bullet'],
    ]);
    expect($bullet)->toContain('<ul')->toContain('<li')->and($bullet)->toContain('Apple');

    $number = PageRenderer::renderBlock([
        'type' => 'list',
        'settings' => ['items' => "One\nTwo", 'style' => 'number'],
    ]);
    expect($number)->toContain('<ol');
});

test('code block does NOT substitute variables · {{ x }} is literal', function () {
    $html = PageRenderer::renderBlock([
        'type'     => 'code',
        'settings' => ['code' => 'const x = {{ id }}', 'language' => 'js'],
    ], ['id' => '99']);
    expect($html)->toContain('{{ id }}')
        ->and($html)->not->toContain('99');
});

test('card picks a tone palette and renders title + body', function () {
    $html = PageRenderer::renderBlock([
        'type' => 'card',
        'settings' => ['title' => 'Heads up', 'body' => 'Body', 'tone' => 'warning'],
    ]);
    expect($html)->toContain('<h3')
        ->and($html)->toContain('Heads up')
        ->and($html)->toContain('#fffbeb');
});

test('columns honour the ratio setting', function () {
    foreach (['1-1' => '1fr 1fr', '1-2' => '1fr 2fr', '2-1' => '2fr 1fr'] as $ratio => $grid) {
        $html = PageRenderer::renderBlock([
            'type' => 'columns',
            'settings' => ['left' => 'A', 'right' => 'B', 'ratio' => $ratio],
        ]);
        expect($html)->toContain($grid);
    }
});

test('columns block renders nested children inside the grid', function () {
    $block = [
        'type'     => 'columns',
        'settings' => ['ratio' => '1-1', 'gap' => 'md'],
        'children' => [
            'left'  => [['type' => 'paragraph', 'settings' => ['text' => 'LEFT-SIDE']]],
            'right' => [['type' => 'paragraph', 'settings' => ['text' => 'RIGHT-SIDE']]],
        ],
    ];
    $html = PageRenderer::renderBlock($block);
    expect($html)->toContain('display:grid')
        ->and($html)->toContain('LEFT-SIDE')
        ->and($html)->toContain('RIGHT-SIDE');
});

test('section block renders the body slot inside a <section>', function () {
    $block = [
        'type'     => 'section',
        'settings' => ['background' => 'tint', 'padding' => 'md'],
        'children' => ['body' => [
            ['type' => 'heading', 'settings' => ['text' => 'Hi', 'level' => 'h2', 'align' => 'left']],
        ]],
    ];
    $html = PageRenderer::renderBlock($block);
    expect($html)->toContain('<section')
        ->and($html)->toContain('<h2')
        ->and($html)->toContain('Hi')
        ->and($html)->toContain('background:#f3f4f6');
});

test('columns-3 renders three nested grid columns', function () {
    $block = [
        'type'     => 'columns-3',
        'settings' => ['gap' => 'md'],
        'children' => [
            'left'   => [['type' => 'paragraph', 'settings' => ['text' => 'A']]],
            'middle' => [['type' => 'paragraph', 'settings' => ['text' => 'B']]],
            'right'  => [['type' => 'paragraph', 'settings' => ['text' => 'C']]],
        ],
    ];
    $html = PageRenderer::renderBlock($block);
    expect($html)->toContain('grid-template-columns:1fr 1fr 1fr')
        ->and($html)->toContain('A')->and($html)->toContain('B')->and($html)->toContain('C');
});

test('conditional block renders children when the bound variable is truthy', function () {
    $block = [
        'type'     => 'conditional',
        'settings' => ['variable' => 'isAdmin', 'mode' => 'truthy'],
        'children' => ['body' => [
            ['type' => 'paragraph', 'settings' => ['text' => 'Welcome admin']],
        ]],
    ];
    expect(PageRenderer::renderBlock($block, ['isAdmin' => true]))->toContain('Welcome admin');
    expect(PageRenderer::renderBlock($block, ['isAdmin' => false]))->not->toContain('Welcome admin');
});

test('conditional block "falsy" mode flips the predicate', function () {
    $block = [
        'type'     => 'conditional',
        'settings' => ['variable' => 'showWarning', 'mode' => 'falsy'],
        'children' => ['body' => [
            ['type' => 'paragraph', 'settings' => ['text' => 'No alerts']],
        ]],
    ];
    expect(PageRenderer::renderBlock($block, ['showWarning' => false]))->toContain('No alerts');
    expect(PageRenderer::renderBlock($block, ['showWarning' => true]))->not->toContain('No alerts');
});

test('conditional block "equals" mode matches the compare setting', function () {
    $block = [
        'type'     => 'conditional',
        'settings' => ['variable' => 'role', 'mode' => 'equals', 'compare' => 'admin'],
        'children' => ['body' => [
            ['type' => 'paragraph', 'settings' => ['text' => 'Admin panel']],
        ]],
    ];
    expect(PageRenderer::renderBlock($block, ['role' => 'admin']))->toContain('Admin panel');
    expect(PageRenderer::renderBlock($block, ['role' => 'user']))->not->toContain('Admin panel');
});

test('image.upload node behaves like image.source · emits {url, filter}', function () {
    $nodes = [
        ['id' => 's', 'type' => 'image.upload', 'settings' => ['url' => 'https://x/y.png']],
        ['id' => 'o', 'type' => 'output',       'settings' => ['name' => 'pic']],
    ];
    $edges = [
        ['from_node' => 's', 'from_socket' => 'image', 'to_node' => 'o', 'to_socket' => 'value'],
    ];
    \LoggedCloud\PageStudio\Support\NodeGraphEngine::flushCache();
    $ctx = \LoggedCloud\PageStudio\Support\NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['pic'])->toBe(['url' => 'https://x/y.png', 'filter' => '']);
});
