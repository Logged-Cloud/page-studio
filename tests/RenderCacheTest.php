<?php

use Illuminate\Support\Facades\Cache;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    Cache::flush();
    config()->set('page-studio.render_cache.enabled', true);
    config()->set('page-studio.render_cache.ttl', 3600);
});

function rcBlocks(string $text = 'hi'): array
{
    return [['type' => 'heading', 'settings' => ['text' => $text, 'level' => 'h1', 'align' => 'left']]];
}

test('a second render with the same blocks + context reads from the cache, not from the block', function () {
    $first  = PageRenderer::render(rcBlocks('one'));
    $second = PageRenderer::render(rcBlocks('one'));

    expect($first)->toBe($second)
        ->and(Cache::get('page-studio:render:html:'.sha1(json_encode(['m' => 'html', 'b' => rcBlocks('one'), 'c' => []]))))
        ->toBe($first);
});

test('different blocks miss the cache and produce different output', function () {
    $a = PageRenderer::render(rcBlocks('one'));
    $b = PageRenderer::render(rcBlocks('two'));

    expect($a)->not->toBe($b)
        ->and($a)->toContain('one')
        ->and($b)->toContain('two');
});

test('different context misses the cache', function () {
    $blocks = [['type' => 'heading', 'settings' => ['text' => '{{ name }}', 'level' => 'h1', 'align' => 'left']]];

    $alice = PageRenderer::render($blocks, ['name' => 'Alice']);
    $bob   = PageRenderer::render($blocks, ['name' => 'Bob']);

    expect($alice)->toContain('Alice')
        ->and($bob)->toContain('Bob')
        ->and($alice)->not->toBe($bob);
});

test('editor decorate mode bypasses the cache so live editing always recomputes', function () {
    PageRenderer::render(rcBlocks('one'), [], decorate: true);

    expect(Cache::get('page-studio:render:html:'.sha1(json_encode(['m' => 'html', 'b' => rcBlocks('one'), 'c' => []]))))
        ->toBeNull();
});

test('email / text / markdown renders keep separate cache entries', function () {
    $blocks = rcBlocks('multi');

    $html = PageRenderer::render($blocks);
    $email = PageRenderer::renderForEmail($blocks);
    $text  = PageRenderer::renderForText($blocks);
    $md    = PageRenderer::renderForMarkdown($blocks);

    foreach (['html', 'email', 'text', 'markdown'] as $mode) {
        $key = 'page-studio:render:'.$mode.':'.sha1(json_encode(['m' => $mode, 'b' => $blocks, 'c' => []]));
        expect(Cache::get($key))->not->toBeNull("mode {$mode} should have written a cache entry");
    }

    // Output between modes is independent; html vs text at least differ.
    expect($html)->not->toBe($text);
});

test('when the cache is disabled the renderer behaves exactly like before', function () {
    config()->set('page-studio.render_cache.enabled', false);

    $blocks = rcBlocks('off');
    $out    = PageRenderer::render($blocks);

    expect($out)->toContain('off')
        ->and(Cache::get('page-studio:render:html:'.sha1(json_encode(['m' => 'html', 'b' => $blocks, 'c' => []]))))
        ->toBeNull();
});
