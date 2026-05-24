<?php

use LoggedCloud\PageStudio\Blocks\Builtin\CardBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ColumnsBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ColumnsThreeBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\HeadingBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\ParagraphBlock;
use LoggedCloud\PageStudio\Blocks\Builtin\TableBlock;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('unsafe built-in blocks declare themselves not email-safe', function () {
    expect(ColumnsBlock::emailSafe())->toBeFalse()
        ->and(ColumnsThreeBlock::emailSafe())->toBeFalse()
        ->and(CardBlock::emailSafe())->toBeFalse();
});

it('safe built-in blocks default to email-safe', function () {
    expect(HeadingBlock::emailSafe())->toBeTrue()
        ->and(ParagraphBlock::emailSafe())->toBeTrue()
        ->and(TableBlock::emailSafe())->toBeTrue();
});

it('blockLibrary excludes unsafe blocks when emailMode is true', function () {
    $pb = new PageBuilder();
    $pb->emailMode = true;

    $flat = collect($pb->blockLibrary)->flatMap(fn ($group) => array_keys($group))->all();
    expect($flat)->not->toContain('columns')
        ->and($flat)->not->toContain('columns-3')
        ->and($flat)->not->toContain('card')
        ->and($flat)->toContain('heading')
        ->and($flat)->toContain('paragraph')
        ->and($flat)->toContain('table');
});

it('blockLibrary still includes everything when emailMode is false', function () {
    $pb = new PageBuilder();
    $pb->emailMode = false;

    $flat = collect($pb->blockLibrary)->flatMap(fn ($group) => array_keys($group))->all();
    expect($flat)->toContain('columns', 'columns-3', 'card');
});

it('addBlock refuses non-email-safe types when emailMode is true', function () {
    \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => true])
        ->call('addBlock', 'columns')
        ->assertSet('blocks', []);
});

it('addBlock still accepts safe types in email mode', function () {
    \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => true])
        ->call('addBlock', 'heading')
        ->assertCount('blocks', 1);
});

it('mount can flip into email mode without a route or page', function () {
    $pb = \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => true]);
    $pb->assertSet('emailMode', true);
});

it('toLibraryEntry surfaces email_safe so palettes can render the gate', function () {
    expect(ColumnsBlock::toLibraryEntry())->toHaveKey('email_safe')
        ->and(ColumnsBlock::toLibraryEntry()['email_safe'])->toBeFalse()
        ->and(HeadingBlock::toLibraryEntry()['email_safe'])->toBeTrue();
});
