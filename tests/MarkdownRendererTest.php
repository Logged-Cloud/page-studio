<?php

use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('headings emit markdown # prefixes per level', function () {
    $md = PageRenderer::renderForMarkdown([
        ['type' => 'heading', 'settings' => ['text' => 'Title',   'level' => 'h1', 'align' => 'left']],
        ['type' => 'heading', 'settings' => ['text' => 'Section', 'level' => 'h2', 'align' => 'left']],
        ['type' => 'heading', 'settings' => ['text' => 'Sub',     'level' => 'h3', 'align' => 'left']],
        ['type' => 'heading', 'settings' => ['text' => 'Tiny',    'level' => 'h4', 'align' => 'left']],
    ], []);
    expect($md)->toContain('# Title')
        ->and($md)->toContain('## Section')
        ->and($md)->toContain('### Sub')
        ->and($md)->toContain('#### Tiny');
});

it('image emits `![alt](src)` markdown image syntax', function () {
    $md = PageRenderer::renderForMarkdown([
        ['type' => 'image', 'settings' => ['src' => 'https://example.com/cat.png', 'alt' => 'A cat']],
    ], []);
    expect($md)->toContain('![A cat](https://example.com/cat.png)');
});

it('button emits `[label](url)` markdown link syntax', function () {
    $md = PageRenderer::renderForMarkdown([
        ['type' => 'button', 'settings' => ['label' => 'Get started', 'href' => 'https://example.com/start']],
    ], []);
    expect($md)->toContain('[Get started](https://example.com/start)');
});

it('quote emits `>` lines and an em-dash citation', function () {
    $md = PageRenderer::renderForMarkdown([
        ['type' => 'quote', 'settings' => ['text' => "Line one\nLine two", 'cite' => 'Socrates']],
    ], []);
    expect($md)->toContain('> Line one')
        ->and($md)->toContain('> Line two')
        ->and($md)->toContain("\u{2014} Socrates");
});

it('table emits a pipe-separated markdown table with a header separator', function () {
    $md = PageRenderer::renderForMarkdown([[
        'type' => 'table',
        'settings' => ['html' => '<table><tr><th>Col A</th><th>Col B</th></tr><tr><td>1</td><td>2</td></tr></table>'],
    ]], []);
    expect($md)->toContain('| Col A | Col B |')
        ->and($md)->toContain('|---|---|')
        ->and($md)->toContain('| 1 | 2 |');
});

it('hero emits `# heading`, subheading and `[label](url)`', function () {
    $md = PageRenderer::renderForMarkdown([[
        'type' => 'hero',
        'settings' => [
            'heading'    => 'Welcome',
            'subheading' => 'Two short sentences.',
            'cta_label'  => 'Sign up',
            'cta_href'   => 'https://example.com/signup',
            'align'      => 'left',
        ],
    ]], []);
    expect($md)->toContain('# Welcome')
        ->and($md)->toContain('Two short sentences.')
        ->and($md)->toContain('[Sign up](https://example.com/signup)');
});

it('layout blocks recurse children correctly into markdown', function () {
    $md = PageRenderer::renderForMarkdown([[
        'type' => 'section', 'settings' => [],
        'children' => [
            'body' => [
                ['type' => 'heading', 'settings' => ['text' => 'Heading', 'level' => 'h1', 'align' => 'left']],
                ['type' => 'button',  'settings' => ['label' => 'Go', 'href' => 'https://example.com']],
            ],
        ],
    ]], []);
    expect($md)->toContain('# Heading')
        ->and($md)->toContain('[Go](https://example.com)');
});

it('renderForMarkdown collapses runaway blank lines and ends with a single newline', function () {
    $md = PageRenderer::renderForMarkdown([
        ['type' => 'spacer', 'settings' => ['size' => 'lg']],
        ['type' => 'spacer', 'settings' => ['size' => 'lg']],
        ['type' => 'paragraph', 'settings' => ['text' => 'After the gap.']],
    ], []);
    expect($md)->not->toMatch("/\n{3,}/")
        ->and(substr($md, -1))->toBe("\n");
});

it('falls back to renderText for blocks without a markdown override (spacer still emits a newline)', function () {
    $md = PageRenderer::renderBlockForMarkdown(['type' => 'spacer', 'settings' => ['size' => 'sm']], []);
    expect($md)->toContain("\n");
});

it('substitutes dotted-path tokens inside button labels and hrefs', function () {
    $md = PageRenderer::renderForMarkdown(
        [[
            'type' => 'button',
            'settings' => ['label' => '{{ user.name }}', 'href' => 'https://example.com/u/{{ user.id }}'],
        ]],
        ['user' => ['name' => 'Charles', 'id' => 42]],
    );
    expect($md)->toContain('[Charles](https://example.com/u/42)');
});
