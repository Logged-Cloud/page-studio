<?php

use LoggedCloud\PageStudio\Blocks\Builtin\CardBlock;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('pins a dark text colour on the card so title and body stay legible on a dark-themed page', function () {
    $block = new CardBlock();
    $html  = $block->render(
        ['title' => 'Fast turnaround', 'subtitle' => 'Most jobs same day', 'tone' => 'neutral'],
        [],
        [],
    );

    expect($html)->toContain('color:#111827')
        ->and($html)->toContain('Fast turnaround');
});

it('pins the same text colour in the email rendering', function () {
    $block = new CardBlock();
    $html  = $block->renderEmail(
        ['title' => 'Fast turnaround', 'subtitle' => '', 'tone' => 'info'],
        [],
        [],
    );

    expect($html)->toContain('color:#111827');
});
