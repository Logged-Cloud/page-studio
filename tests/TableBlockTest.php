<?php

use LoggedCloud\PageStudio\Blocks\Builtin\TableBlock;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('renders the stored HTML verbatim when no tokens are present', function () {
    $block = new TableBlock();
    $html  = $block->render(
        ['html' => '<table><tr><td>Cell</td></tr></table>'],
        [],
        [],
    );
    expect($html)->toBe('<table><tr><td>Cell</td></tr></table>');
});

it('substitutes dotted tokens inside the table HTML against the context', function () {
    $block = new TableBlock();
    $html  = $block->render(
        ['html' => '<table><tr><td>{{ user.name }}</td><td>{{ user.email }}</td></tr></table>'],
        [],
        ['user' => ['name' => 'Charles', 'email' => 'c@example.com']],
    );
    expect($html)->toContain('<td>Charles</td>')
        ->and($html)->toContain('<td>c@example.com</td>');
});

it('exposes a usable default + textarea setting', function () {
    $defaults = TableBlock::settings();
    expect($defaults)->toHaveKey('html')
        ->and($defaults['html']['kind'])->toBe('textarea')
        ->and($defaults['html']['default'])->toContain('<table>');
});

it('PageRenderer routes the `table` type through the BlockType registry', function () {
    $html = PageRenderer::renderBlock(
        ['type' => 'table', 'settings' => ['html' => '<table><tr><td>X</td></tr></table>']],
        [],
    );
    expect($html)->toContain('<table>');
});
