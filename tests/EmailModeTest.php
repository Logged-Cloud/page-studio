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

it('every built-in block declares itself email-safe', function () {
    // Layout blocks ship table-based `renderEmail()` overrides so they
    // survive Outlook + Gmail; everything else is safe by default.
    expect(ColumnsBlock::emailSafe())->toBeTrue()
        ->and(ColumnsThreeBlock::emailSafe())->toBeTrue()
        ->and(CardBlock::emailSafe())->toBeTrue()
        ->and(HeadingBlock::emailSafe())->toBeTrue()
        ->and(ParagraphBlock::emailSafe())->toBeTrue()
        ->and(TableBlock::emailSafe())->toBeTrue();
});

it('blockLibrary includes all built-ins when emailMode is true', function () {
    $pb = new PageBuilder();
    $pb->emailMode = true;

    $flat = collect($pb->blockLibrary)->flatMap(fn ($group) => array_keys($group))->all();
    expect($flat)->toContain('heading', 'paragraph', 'columns', 'card', 'columns-3', 'table');
});

it('blockLibrary still includes everything when emailMode is false', function () {
    $pb = new PageBuilder();
    $pb->emailMode = false;

    $flat = collect($pb->blockLibrary)->flatMap(fn ($group) => array_keys($group))->all();
    expect($flat)->toContain('columns', 'columns-3', 'card');
});

it('addBlock refuses a block whose schema flips email_safe off (host-app gate)', function () {
    // Inject an unsafe entry the way a host app might · the package's
    // built-ins are all safe now, but the server-side guard should still
    // refuse anything the schema flips off.
    $lib = config('page-studio.blocks');
    $lib['unsafe.demo'] = ['email_safe' => false, 'settings' => []];
    config(['page-studio.blocks' => $lib]);

    \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => true])
        ->call('addBlock', 'unsafe.demo')
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
        ->and(ColumnsBlock::toLibraryEntry()['email_safe'])->toBeTrue()
        ->and(HeadingBlock::toLibraryEntry()['email_safe'])->toBeTrue();
});
