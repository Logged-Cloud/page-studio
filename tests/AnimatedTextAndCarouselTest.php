<?php

use LoggedCloud\PageStudio\Blocks\Builtin\AnimatedTextBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ImageCarouselBlock;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('animated_text renders all phrases as JSON for the Alpine factory', function () {
    $block = new AnimatedTextBlock();
    $html  = $block->render(['items' => "Agents\nAirlines\nTour Operators", 'mode' => 'typewriter'], [], []);

    expect($html)
        ->toContain('Agents')
        ->toContain('Airlines')
        ->toContain('Tour Operators')
        ->toContain('x-data');
});

it('animated_text parses per-phrase colour prefix', function () {
    $block = new AnimatedTextBlock();
    $html  = $block->render(['items' => "#E11D48: Agents\n#0EA5E9: Airlines", 'mode' => 'roller-up'], [], []);

    expect($html)
        ->toContain('#E11D48')
        ->toContain('#0EA5E9');
});

it('animated_text keeps phrases without a colour prefix on the default colour', function () {
    $block = new AnimatedTextBlock();
    $html  = $block->render(['items' => "Plain text", 'color' => '#10B981', 'mode' => 'typewriter'], [], []);

    expect($html)->toContain('#10B981');
});

it('animated_text typewriter caret can be turned off', function () {
    $block = new AnimatedTextBlock();
    $on    = $block->render(['items' => 'x', 'mode' => 'typewriter', 'caret' => true], [], []);
    $off   = $block->render(['items' => 'x', 'mode' => 'typewriter', 'caret' => false], [], []);

    expect($on)->toContain('ps-anim-caret')
        ->and($off)->not->toContain('ps-anim-caret');
});

it('animated_text email mode renders the first phrase as static HTML', function () {
    $block = new AnimatedTextBlock();
    $email = $block->renderEmail(['items' => "Agents\nAirlines", 'color' => '#2C66E8'], [], []);

    expect($email)->toContain('Agents')
        ->and($email)->not->toContain('x-data');
});

it('animated_text text mode joins phrases with a slash', function () {
    $block = new AnimatedTextBlock();
    expect($block->renderText(['items' => "Agents\nAirlines"], [], []))->toBe("Agents / Airlines\n\n");
});

it('animated_text substitutes variables inside phrase text', function () {
    $block = new AnimatedTextBlock();
    $html  = $block->render(['items' => '{{ role }}', 'mode' => 'typewriter'], [], ['role' => 'Agent']);
    expect($html)->toContain('Agent');
});

it('image_carousel coverflow renders one img per slide + pagination dots', function () {
    $block = new ImageCarouselBlock();
    $html  = $block->render(['images' => "/a.png | A\n/b.png | B\n/c.png | C", 'mode' => 'coverflow'], [], []);

    expect(substr_count($html, '<img'))->toBe(3)
        ->and($html)->toContain('alt="A"')
        ->and($html)->toContain('alt="B"')
        ->and($html)->toContain('Go to slide 1');
});

it('image_carousel marquee doubles the slide set for the seamless loop', function () {
    $block = new ImageCarouselBlock();
    $html  = $block->render(['images' => "/a.png\n/b.png\n/c.png", 'mode' => 'marquee'], [], []);

    // Three slides rendered twice = 6 imgs total.
    expect(substr_count($html, '<img'))->toBe(6);
});

it('image_carousel email mode emits a single-column table, no JS', function () {
    $block = new ImageCarouselBlock();
    $email = $block->renderEmail(['images' => "/a.png | A\n/b.png | B", 'mode' => 'coverflow'], [], []);

    expect($email)
        ->toContain('role="presentation"')
        ->and($email)->toContain('/a.png')
        ->and($email)->toContain('/b.png')
        ->and($email)->not->toContain('x-data');
});

it('image_carousel markdown mode renders ![alt](src) lines', function () {
    $block = new ImageCarouselBlock();
    expect($block->renderMarkdown(['images' => "/a.png | An A"], [], []))
        ->toBe("![An A](/a.png)\n\n");
});

it('PageRenderer routes both new types through the BlockType registry', function () {
    $html = PageRenderer::render([
        ['type' => 'animated_text',  'settings' => ['items' => "X\nY", 'mode' => 'typewriter']],
        ['type' => 'image_carousel', 'settings' => ['images' => "/a.png", 'mode' => 'marquee']],
    ]);

    expect($html)->toContain('x-data')
        ->and($html)->toContain('/a.png');
});
