<?php

use LoggedCloud\PageStudio\Blocks\Builtin\AccordionBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\EmbedBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\TabsBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\VideoBlock;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('Embed converts a YouTube watch URL to the /embed/ form', function () {
    $html = (new EmbedBlock())->render(
        ['url' => 'https://www.youtube.com/watch?v=abc123XYZ', 'aspect' => '16-9'],
        [], [],
    );
    expect($html)->toContain('src="https://www.youtube.com/embed/abc123XYZ"');
});

it('Embed converts Vimeo and Loom URLs to their player URLs', function () {
    $vimeo = (new EmbedBlock())->render(
        ['url' => 'https://vimeo.com/76979871', 'aspect' => '16-9'],
        [], [],
    );
    $loom = (new EmbedBlock())->render(
        ['url' => 'https://www.loom.com/share/abc-def_123', 'aspect' => '16-9'],
        [], [],
    );
    expect($vimeo)->toContain('src="https://player.vimeo.com/video/76979871"')
        ->and($loom)->toContain('src="https://www.loom.com/embed/abc-def_123"');
});

it('Embed uses 56.25% padding for 16-9 and 75% for 4-3', function () {
    $sixteenNine = (new EmbedBlock())->render(['url' => 'https://x.test', 'aspect' => '16-9'], [], []);
    $fourThree   = (new EmbedBlock())->render(['url' => 'https://x.test', 'aspect' => '4-3'],  [], []);
    $oneOne      = (new EmbedBlock())->render(['url' => 'https://x.test', 'aspect' => '1-1'],  [], []);
    expect($sixteenNine)->toContain('padding-bottom:56.25%')
        ->and($fourThree)->toContain('padding-bottom:75%')
        ->and($oneOne)->toContain('padding-bottom:100%');
});

it('Embed is not emailSafe', function () {
    expect(EmbedBlock::emailSafe())->toBeFalse();
});

it('Video renders a <video> tag with controls; poster + autoplay force muted', function () {
    $html = (new VideoBlock())->render(
        [
            'src'      => 'https://x.test/clip.mp4',
            'poster'   => 'https://x.test/poster.jpg',
            'controls' => true,
            'autoplay' => true,
            'loop'     => true,
        ],
        [], [],
    );
    expect($html)->toContain('<video')
        ->and($html)->toContain('src="https://x.test/clip.mp4"')
        ->and($html)->toContain('poster="https://x.test/poster.jpg"')
        ->and($html)->toContain(' controls')
        ->and($html)->toContain(' autoplay')
        ->and($html)->toContain(' muted')
        ->and($html)->toContain(' loop');
});

it('Video is not emailSafe', function () {
    expect(VideoBlock::emailSafe())->toBeFalse();
});

it('Accordion parses Title|Body lines, emits <details>, first opens by default', function () {
    $html = (new AccordionBlock())->render(
        ['items' => "First?|Yes.\nSecond?|Also yes.", 'expanded' => 'first'],
        [], [],
    );
    expect($html)->toContain('<details open')
        ->and(substr_count($html, '<details'))->toBe(2)
        ->and(substr_count($html, '<details open'))->toBe(1)
        ->and($html)->toContain('First?')
        ->and($html)->toContain('Yes.')
        ->and($html)->toContain('Second?');
});

it('Accordion email render emits stacked headings + paragraphs (no <details>)', function () {
    $html = (new AccordionBlock())->renderEmail(
        ['items' => "First?|Yes.\nSecond?|Also yes.", 'expanded' => 'first'],
        [], [],
    );
    expect($html)->toContain('<table')
        ->and($html)->not->toContain('<details')
        ->and($html)->toContain('<strong')
        ->and($html)->toContain('First?')
        ->and($html)->toContain('Yes.')
        ->and($html)->toContain('Second?');
});

it('Accordion plain text renders Q:/A: pairs', function () {
    $text = (new AccordionBlock())->renderText(
        ['items' => "First?|Yes.\nSecond?|Also.", 'expanded' => 'first'],
        [], [],
    );
    expect($text)->toBe("Q: First?\nA: Yes.\n\nQ: Second?\nA: Also.\n\n");
});

it('Tabs web render emits Alpine x-data and three slot bodies with x-show', function () {
    $html = (new TabsBlock())->render(
        ['labels' => "One\nTwo\nThree", 'active' => 1],
        [
            'tab1' => [['type' => 'paragraph', 'settings' => ['text' => 'body-one']]],
            'tab2' => [['type' => 'paragraph', 'settings' => ['text' => 'body-two']]],
            'tab3' => [['type' => 'paragraph', 'settings' => ['text' => 'body-three']]],
        ],
        [],
    );
    expect($html)->toContain('x-data="{ active: 1 }"')
        ->and($html)->toContain('x-show="active === 0"')
        ->and($html)->toContain('x-show="active === 1"')
        ->and($html)->toContain('x-show="active === 2"')
        ->and($html)->toContain('body-one')
        ->and($html)->toContain('body-two')
        ->and($html)->toContain('body-three')
        ->and($html)->toContain('One')
        ->and($html)->toContain('Two')
        ->and($html)->toContain('Three');
});

it('Tabs email render stacks all three slots with their labels as headings', function () {
    $html = (new TabsBlock())->renderEmail(
        ['labels' => "One\nTwo\nThree", 'active' => 0],
        [
            'tab1' => [['type' => 'paragraph', 'settings' => ['text' => 'body-one']]],
            'tab2' => [['type' => 'paragraph', 'settings' => ['text' => 'body-two']]],
            'tab3' => [['type' => 'paragraph', 'settings' => ['text' => 'body-three']]],
        ],
        [],
    );
    expect($html)->not->toContain('x-data')
        ->and($html)->not->toContain('x-show')
        ->and($html)->toContain('<h3')
        ->and($html)->toContain('One')
        ->and($html)->toContain('Two')
        ->and($html)->toContain('Three')
        ->and($html)->toContain('body-one')
        ->and($html)->toContain('body-two')
        ->and($html)->toContain('body-three');
});

it('Tabs plain text flattens all slots with their labels', function () {
    $text = (new TabsBlock())->renderText(
        ['labels' => "One\nTwo\nThree", 'active' => 0],
        [
            'tab1' => [['type' => 'paragraph', 'settings' => ['text' => 'body-one']]],
            'tab2' => [['type' => 'paragraph', 'settings' => ['text' => 'body-two']]],
            'tab3' => [['type' => 'paragraph', 'settings' => ['text' => 'body-three']]],
        ],
        [],
    );
    expect($text)->toContain('# One')
        ->and($text)->toContain('# Two')
        ->and($text)->toContain('# Three')
        ->and($text)->toContain('body-one')
        ->and($text)->toContain('body-two')
        ->and($text)->toContain('body-three');
});

it('all four new blocks are registered in page-studio.blocks', function () {
    $lib = config('page-studio.blocks', []);
    expect($lib)->toHaveKey('embed')
        ->and($lib)->toHaveKey('video')
        ->and($lib)->toHaveKey('accordion')
        ->and($lib)->toHaveKey('tabs');
});
