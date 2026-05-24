<?php

use LoggedCloud\PageStudio\Support\HtmlImporter;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('returns an empty array for empty input', function () {
    expect(HtmlImporter::toBlocks(''))->toBe([])
        ->and(HtmlImporter::toBlocks('   '))->toBe([]);
});

it('maps headings to heading blocks at the right level', function () {
    $blocks = HtmlImporter::toBlocks('<h1>Title</h1><h2>Subtitle</h2><h3>Section</h3>');
    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]['type'])->toBe('heading')
        ->and($blocks[0]['settings']['level'])->toBe('h1')
        ->and($blocks[0]['settings']['text'])->toBe('Title')
        ->and($blocks[1]['settings']['level'])->toBe('h2')
        ->and($blocks[2]['settings']['level'])->toBe('h3');
});

it('maps paragraphs to paragraph blocks', function () {
    $blocks = HtmlImporter::toBlocks('<p>First line.</p><p>Second line.</p>');
    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['settings']['text'])->toBe('First line.')
        ->and($blocks[1]['settings']['text'])->toBe('Second line.');
});

it('maps an image tag to an image block carrying src + alt', function () {
    $blocks = HtmlImporter::toBlocks('<img src="https://example.com/a.png" alt="Hero shot">');
    expect($blocks[0]['type'])->toBe('image')
        ->and($blocks[0]['settings']['src'])->toBe('https://example.com/a.png')
        ->and($blocks[0]['settings']['alt'])->toBe('Hero shot');
});

it('maps unordered + ordered lists to list blocks with the right style', function () {
    $blocks = HtmlImporter::toBlocks('<ul><li>One</li><li>Two</li></ul><ol><li>A</li><li>B</li></ol>');
    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('list')
        ->and($blocks[0]['settings']['style'])->toBe('bullet')
        ->and($blocks[0]['settings']['items'])->toBe("One\nTwo")
        ->and($blocks[1]['settings']['style'])->toBe('number');
});

it('maps hr to divider, blockquote to quote, pre to code', function () {
    $blocks = HtmlImporter::toBlocks('<hr><blockquote>Famous quote.</blockquote><pre>console.log(1)</pre>');
    expect($blocks[0]['type'])->toBe('divider')
        ->and($blocks[1]['type'])->toBe('quote')
        ->and($blocks[1]['settings']['text'])->toBe('Famous quote.')
        ->and($blocks[2]['type'])->toBe('code')
        ->and($blocks[2]['settings']['code'])->toBe('console.log(1)');
});

it('preserves a table by storing the raw outer HTML in a table block', function () {
    $blocks = HtmlImporter::toBlocks('<table><tr><th>Col</th></tr><tr><td>Cell</td></tr></table>');
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('table')
        ->and($blocks[0]['settings']['html'])->toContain('<table>')
        ->and($blocks[0]['settings']['html'])->toContain('Cell');
});

it('falls back to a paragraph for unknown / generic block-level tags', function () {
    $blocks = HtmlImporter::toBlocks('<div>Lone text</div>');
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['settings']['text'])->toBe('Lone text');
});

it('drops bare <br> tags', function () {
    $blocks = HtmlImporter::toBlocks('<p>First</p><br><p>Second</p>');
    expect($blocks)->toHaveCount(2)
        ->and(collect($blocks)->pluck('type'))->not->toContain('br');
});

it('imports a realistic CKEditor blob end-to-end', function () {
    $html = <<<'HTML'
<h1>Welcome</h1>
<p>Hi there, the booking for {{ user.firstname }} is confirmed.</p>
<ul>
  <li>Check-in: 3pm</li>
  <li>Check-out: 11am</li>
</ul>
<p><img src="https://placehold.co/600x300" alt="Hotel"></p>
<hr>
<p>See you soon.</p>
HTML;

    $blocks = HtmlImporter::toBlocks($html);
    $types  = array_column($blocks, 'type');

    expect($types)->toContain('heading', 'paragraph', 'list', 'divider');
    // The {{ token }} survives in the paragraph text so the renderer can
    // substitute it at output time.
    $body = collect($blocks)->firstWhere('type', 'paragraph');
    expect($body['settings']['text'])->toContain('{{ user.firstname }}');
});
