<?php

use LoggedCloud\PageStudio\Blocks\BlockRegistry;
use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class StripFallbackTestBlock extends BlockType
{
    public static function key(): string   { return 'demo.strip'; }
    public static function label(): string { return 'Strip'; }
    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        return '<div><strong>Hello</strong> &amp; <em>world</em></div>';
    }
    // No renderText override · the walker should fall back to strip_tags.
}

it('headings render as markdown-style # prefixes', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'heading', 'settings' => ['text' => 'Title',  'level' => 'h1', 'align' => 'left']],
        ['type' => 'heading', 'settings' => ['text' => 'Section', 'level' => 'h3', 'align' => 'left']],
    ], []);
    expect($text)->toContain('# Title')
        ->and($text)->toContain('### Section');
});

it('paragraphs are joined with a blank line', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'paragraph', 'settings' => ['text' => 'First line.']],
        ['type' => 'paragraph', 'settings' => ['text' => 'Second line.']],
    ], []);
    expect($text)->toBe("First line.\n\nSecond line.\n");
});

it('substitutes dotted tokens in paragraph text', function () {
    $text = PageRenderer::renderForText(
        [['type' => 'paragraph', 'settings' => ['text' => 'Hi {{ user.name }}, your id is {{ user.id }}.']]],
        ['user' => ['name' => 'Charles', 'id' => 42]],
    );
    expect($text)->toContain('Hi Charles, your id is 42.');
});

it('button renders as `Label: url` so it survives plain text', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'button', 'settings' => ['label' => 'Get started', 'href' => 'https://example.com/start']],
    ], []);
    expect($text)->toContain('Get started: https://example.com/start');
});

it('image renders as `[image: alt] url`', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'image', 'settings' => ['src' => 'https://example.com/a.png', 'alt' => 'Hero']],
    ], []);
    expect($text)->toContain('[image: Hero] https://example.com/a.png');
});

it('list emits `-` bullets and `1.` numbers', function () {
    $bullet = PageRenderer::renderForText([
        ['type' => 'list', 'settings' => ['items' => "One\nTwo\nThree", 'style' => 'bullet']],
    ], []);
    $number = PageRenderer::renderForText([
        ['type' => 'list', 'settings' => ['items' => "Alpha\nBeta", 'style' => 'number']],
    ], []);
    expect($bullet)->toContain("- One")->toContain("- Two")->toContain("- Three")
        ->and($number)->toContain("1. Alpha")->toContain("2. Beta");
});

it('divider renders as three hyphens', function () {
    $text = PageRenderer::renderForText([['type' => 'divider', 'settings' => []]], []);
    expect($text)->toContain('---');
});

it('quote prefixes lines with > and emits a citation line', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'quote', 'settings' => ['text' => 'The unexamined life is not worth living.', 'cite' => 'Socrates']],
    ], []);
    expect($text)->toContain('> The unexamined life is not worth living.')
        ->and($text)->toContain('- Socrates');
});

it('code wraps in markdown fences with the language', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'code', 'settings' => ['code' => "console.log('hi')", 'language' => 'js']],
    ], []);
    expect($text)->toContain('```js')
        ->and($text)->toContain("console.log('hi')");
});

it('layout blocks recurse through children in plain text', function () {
    $text = PageRenderer::renderForText([[
        'type' => 'columns', 'settings' => [],
        'children' => [
            'left'  => [['type' => 'paragraph', 'settings' => ['text' => 'Left side']]],
            'right' => [['type' => 'paragraph', 'settings' => ['text' => 'Right side']]],
        ],
    ]], []);
    expect($text)->toContain('Left side')
        ->and($text)->toContain('Right side');
});

it('card emits title + subtitle then body', function () {
    $text = PageRenderer::renderForText([[
        'type' => 'card',
        'settings' => ['title' => 'Heads up', 'subtitle' => 'Important', 'tone' => 'warning'],
        'children' => ['body' => [['type' => 'paragraph', 'settings' => ['text' => 'Body copy here.']]]],
    ]], []);
    expect($text)->toContain('Heads up')
        ->and($text)->toContain('Important')
        ->and($text)->toContain('Body copy here.');
});

it('conditional block emits body only when satisfied', function () {
    $shown = PageRenderer::renderForText([[
        'type' => 'conditional',
        'settings' => ['variable' => 'show', 'mode' => 'truthy'],
        'children' => ['body' => [['type' => 'paragraph', 'settings' => ['text' => 'Visible!']]]],
    ]], ['show' => true]);
    $hidden = PageRenderer::renderForText([[
        'type' => 'conditional',
        'settings' => ['variable' => 'show', 'mode' => 'truthy'],
        'children' => ['body' => [['type' => 'paragraph', 'settings' => ['text' => 'Hidden!']]]],
    ]], ['show' => false]);

    expect($shown)->toContain('Visible!')
        ->and($hidden)->not->toContain('Hidden!');
});

it('table renders rows as tab-separated text', function () {
    $text = PageRenderer::renderForText([[
        'type' => 'table',
        'settings' => ['html' => '<table><tr><th>Col A</th><th>Col B</th></tr><tr><td>1</td><td>2</td></tr></table>'],
    ]], []);
    expect($text)->toContain("Col A\tCol B")
        ->and($text)->toContain("1\t2");
});

it('falls back to strip_tags() for blocks without a renderText override', function () {
    BlockRegistry::register(StripFallbackTestBlock::class);
    $text = PageRenderer::renderForText([['type' => 'demo.strip', 'settings' => []]], []);
    expect($text)->toContain('Hello & world');
});

it('collapses runaway blank lines and trims to a single trailing newline', function () {
    $text = PageRenderer::renderForText([
        ['type' => 'spacer', 'settings' => ['size' => 'lg']],
        ['type' => 'spacer', 'settings' => ['size' => 'lg']],
        ['type' => 'paragraph', 'settings' => ['text' => 'After the gap.']],
    ], []);
    // Should not have 3+ blank lines in a row.
    expect($text)->not->toMatch("/\n{3,}/")
        ->and(substr($text, -1))->toBe("\n");
});
