<?php

use LoggedCloud\PageStudio\Blocks\Builtin\ButtonBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\HeroBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\SignatureBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\SocialBlock;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('Button renderEmail wraps the link in a single-cell table', function () {
    $html = (new ButtonBlock())->renderEmail(
        ['label' => 'Buy now', 'href' => 'https://example.com', 'variant' => 'primary'],
        [],
        [],
    );
    expect($html)->toContain('<table')
        ->and($html)->toContain('bgcolor="#2C66E8"')
        ->and($html)->toContain('href="https://example.com"')
        ->and($html)->toContain('Buy now');
});

it('Signature web render stacks signoff / name / title / contacts', function () {
    $html = (new SignatureBlock())->render(
        ['signoff' => 'Cheers,', 'name' => 'Charles', 'title' => 'Founder', 'email' => 'c@x', 'phone' => '+44'],
        [], [],
    );
    expect($html)->toContain('Cheers,')
        ->and($html)->toContain('Charles')
        ->and($html)->toContain('Founder')
        ->and($html)->toContain('c@x')
        ->and($html)->toContain('+44');
});

it('Signature email render uses a table layout', function () {
    $html = (new SignatureBlock())->renderEmail(
        ['signoff' => 'Cheers,', 'name' => 'Charles', 'title' => 'Founder', 'email' => 'c@x', 'phone' => ''],
        [], [],
    );
    expect($html)->toContain('<table')
        ->and($html)->toContain('font-family')
        ->and($html)->toContain('Charles');
});

it('Signature plain-text joins non-empty lines with newlines', function () {
    $text = (new SignatureBlock())->renderText(
        ['signoff' => 'Cheers,', 'name' => 'Charles', 'title' => '', 'email' => 'c@x', 'phone' => ''],
        [], [],
    );
    expect($text)->toBe("Cheers,\nCharles\nc@x\n\n");
});

it('Social links parses Name|URL lines and emits anchors', function () {
    $html = (new SocialBlock())->render(
        ['links' => "Twitter|https://twitter.com/x\nGithub|https://github.com/x", 'align' => 'center'],
        [], [],
    );
    expect($html)->toContain('href="https://twitter.com/x"')
        ->and($html)->toContain('href="https://github.com/x"')
        ->and($html)->toContain('Twitter')
        ->and($html)->toContain('Github')
        ->and($html)->toContain('text-align:center');
});

it('Social links email render uses a table row with cells', function () {
    $html = (new SocialBlock())->renderEmail(
        ['links' => "Twitter|https://t/x\nGithub|https://g/x", 'align' => 'left'],
        [], [],
    );
    expect($html)->toContain('<table')
        ->and(substr_count($html, '<td'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('href="https://t/x"');
});

it('Social links skips malformed lines (missing pipe or empty parts)', function () {
    $html = (new SocialBlock())->render(
        ['links' => "Twitter|https://x\njust-text\n|missing-name\nGithub|", 'align' => 'left'],
        [], [],
    );
    expect($html)->toContain('Twitter')
        ->and($html)->not->toContain('just-text')
        ->and($html)->not->toContain('missing-name')
        ->and(substr_count($html, '<a'))->toBe(1);
});

it('Hero web render stacks image / heading / subheading / CTA', function () {
    $html = (new HeroBlock())->render(
        [
            'heading'    => 'Welcome aboard',
            'subheading' => 'Two sentences, max.',
            'image'      => 'https://x.test/hero.png',
            'cta_label'  => 'Get started',
            'cta_href'   => 'https://x.test/start',
            'align'      => 'center',
        ],
        [], [],
    );
    expect($html)->toContain('src="https://x.test/hero.png"')
        ->and($html)->toContain('Welcome aboard')
        ->and($html)->toContain('Two sentences, max.')
        ->and($html)->toContain('href="https://x.test/start"')
        ->and($html)->toContain('Get started')
        ->and($html)->toContain('text-align:center');
});

it('Hero email render uses a table + nested button table', function () {
    $html = (new HeroBlock())->renderEmail(
        [
            'heading'    => 'Hi there',
            'subheading' => 'Quick intro',
            'image'      => 'https://x.test/h.png',
            'cta_label'  => 'Read more',
            'cta_href'   => 'https://x.test/m',
        ],
        [], [],
    );
    expect(substr_count($html, '<table'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('Hi there')
        ->and($html)->toContain('Read more')
        ->and($html)->toContain('href="https://x.test/m"');
});

it('Hero plain-text emits heading then copy then CTA line', function () {
    $text = (new HeroBlock())->renderText(
        [
            'heading'    => 'Welcome',
            'subheading' => 'Some prose.',
            'cta_label'  => 'Sign up',
            'cta_href'   => 'https://x.test/u',
        ],
        [], [],
    );
    expect($text)->toContain('# Welcome')
        ->and($text)->toContain('Some prose.')
        ->and($text)->toContain('Sign up: https://x.test/u');
});

it('all three new blocks are registered + email-safe', function () {
    expect(SignatureBlock::emailSafe())->toBeTrue()
        ->and(SocialBlock::emailSafe())->toBeTrue()
        ->and(HeroBlock::emailSafe())->toBeTrue();

    $lib = config('page-studio.blocks', []);
    expect($lib)->toHaveKey('signature')
        ->and($lib)->toHaveKey('social')
        ->and($lib)->toHaveKey('hero');
});

it('renderForEmail picks the table-shaped button over the inline-anchor render', function () {
    $html = PageRenderer::renderForEmail(
        [['type' => 'button', 'settings' => ['label' => 'Go', 'href' => 'https://x', 'variant' => 'primary']]],
        [],
    );
    expect($html)->toContain('<table')
        ->and($html)->not->toContain('display:inline-block;background:var(--accent');
});
