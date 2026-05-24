<?php

use LoggedCloud\PageStudio\Blocks\Builtin\CardBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ColumnsBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ColumnsThreeBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\HeadingBlock;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('renderForEmail prefers renderEmail() over render() for blocks that override it', function () {
    $blocks = [[
        'id'    => 'c1',
        'type'  => 'columns',
        'settings' => ['ratio' => '1-1', 'gap' => 'md'],
        'children' => ['left' => [], 'right' => []],
    ]];
    $html = PageRenderer::renderForEmail($blocks, []);
    expect($html)->toContain('<table')
        ->and($html)->toContain('role="presentation"')
        ->and($html)->not->toContain('display:grid');
});

it('columns email render places left + right cells at the picked ratio', function () {
    $block = new ColumnsBlock();
    $html  = $block->renderEmail(
        ['ratio' => '1-2', 'gap' => 'md'],
        ['left' => [], 'right' => []],
        [],
    );
    expect($html)->toContain('width="33%"')
        ->and($html)->toContain('width="67%"')
        ->and($html)->toContain('valign="top"');
});

it('three-columns email render produces three table cells', function () {
    $block = new ColumnsThreeBlock();
    $html  = $block->renderEmail(['gap' => 'lg'], ['left' => [], 'middle' => [], 'right' => []], []);
    expect(substr_count($html, '<td'))->toBe(3)
        ->and($html)->toContain('<table');
});

it('card email render emits a two-cell table with the accent stripe', function () {
    $block = new CardBlock();
    $html  = $block->renderEmail(
        ['title' => 'Heads up', 'subtitle' => 'Important', 'tone' => 'warning'],
        ['body' => []],
        [],
    );
    expect($html)->toContain('<table')
        ->and($html)->toContain('bgcolor="#f59e0b"')   // warning border
        ->and($html)->toContain('bgcolor="#fffbeb"')   // warning bg
        ->and($html)->toContain('Heads up')
        ->and($html)->toContain('Important');
});

it('renderForEmail falls back to render() for blocks without an override', function () {
    $blocks = [[
        'id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'Hello', 'level' => 'h2', 'align' => 'left'],
    ]];
    // HeadingBlock has no renderEmail · the renderer drops through to render().
    $html = PageRenderer::renderForEmail($blocks, []);
    expect($html)->toContain('<h2')
        ->and($html)->toContain('Hello');
});

it('renderForEmail recurses children through renderChildrenForEmail', function () {
    $blocks = [[
        'id' => 'c1', 'type' => 'columns', 'settings' => [],
        'children' => [
            'left'  => [['id' => 'hL', 'type' => 'heading', 'settings' => ['text' => 'L', 'level' => 'h3', 'align' => 'left']]],
            'right' => [['id' => 'hR', 'type' => 'heading', 'settings' => ['text' => 'R', 'level' => 'h3', 'align' => 'left']]],
        ],
    ]];
    $html = PageRenderer::renderForEmail($blocks, []);
    expect($html)->toContain('<table')
        ->and($html)->toContain('L</h3>')
        ->and($html)->toContain('R</h3>');
});

it('renderForEmail substitutes dotted tokens just like render()', function () {
    $blocks = [[
        'id' => 'p', 'type' => 'paragraph', 'settings' => ['text' => 'Hi {{ user.name }}'],
    ]];
    $html = PageRenderer::renderForEmail($blocks, ['user' => ['name' => 'Charles']]);
    expect($html)->toContain('Hi Charles');
});

it('renderForEmail on an empty block array returns an empty string', function () {
    expect(PageRenderer::renderForEmail([], []))->toBe('');
});

it('renderEmail returns null by default so unmarked blocks fall through cleanly', function () {
    $block = new HeadingBlock();
    expect($block->renderEmail(['text' => 'x'], [], []))->toBeNull();
});
