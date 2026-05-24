<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('mount initialises meta with the three default keys', function () {
    $pb = new PageBuilder();
    $pb->mount();
    expect($pb->meta)->toEqual(['subject' => '', 'preheader' => '', 'replyTo' => '']);
});

it('mount loads meta from a stored Page row', function () {
    $page = Page::create([
        'route_id' => null,
        'blocks'   => [],
        'meta'     => ['subject' => 'Hi!', 'preheader' => 'a glance', 'replyTo' => 'r@x'],
    ]);

    $pb = new PageBuilder();
    $pb->mount(null, $page->id);

    expect($pb->meta['subject'])->toBe('Hi!')
        ->and($pb->meta['preheader'])->toBe('a glance')
        ->and($pb->meta['replyTo'])->toBe('r@x');
});

it('mount fills missing meta keys from the defaults when the row stored a partial map', function () {
    $page = Page::create([
        'route_id' => null,
        'blocks'   => [],
        'meta'     => ['subject' => 'Stored subject'],
    ]);

    $pb = new PageBuilder();
    $pb->mount(null, $page->id);

    expect($pb->meta['subject'])->toBe('Stored subject')
        ->and($pb->meta['preheader'])->toBe('')
        ->and($pb->meta['replyTo'])->toBe('');
});

it('save() persists meta alongside blocks on a route-bound page', function () {
    $route = RouteDefinition::create([
        'name' => 'em-test', 'path_template' => '/em', 'method' => 'get',
    ]);

    \Livewire\Livewire::test(PageBuilder::class, ['routeId' => $route->id, 'emailMode' => true])
        ->set('meta', ['subject' => 'New subject', 'preheader' => 'Hint', 'replyTo' => 'r@x'])
        ->call('save');

    $page = Page::where('route_id', $route->id)->first();
    expect($page)->not->toBeNull()
        ->and($page->meta)->toEqual(['subject' => 'New subject', 'preheader' => 'Hint', 'replyTo' => 'r@x']);
});

it('save() persists meta on a pageId-bound page', function () {
    $page = Page::create(['route_id' => null, 'blocks' => [], 'meta' => []]);

    \Livewire\Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->set('meta.subject', 'From the builder')
        ->call('save');

    $fresh = Page::find($page->id);
    expect($fresh->meta['subject'])->toBe('From the builder');
});

it('saved event payload includes meta in ephemeral mode', function () {
    \Livewire\Livewire::test(PageBuilder::class)
        ->set('meta.subject', 'Hello')
        ->call('save')
        ->assertDispatched('page-studio:page:saved', fn ($event, $params) => ($params['meta']['subject'] ?? null) === 'Hello');
});

it('saved event payload includes meta when bound to a page', function () {
    $page = Page::create(['route_id' => null, 'blocks' => [], 'meta' => []]);

    \Livewire\Livewire::test(PageBuilder::class, ['pageId' => $page->id])
        ->set('meta', ['subject' => 'X', 'preheader' => 'Y', 'replyTo' => 'r@x'])
        ->call('save')
        ->assertDispatched('page-studio:page:saved', fn ($event, $params) =>
            ($params['meta']['subject'] ?? null) === 'X'
            && ($params['meta']['preheader'] ?? null) === 'Y'
        );
});

it('email-meta field strip only renders when emailMode is true', function () {
    \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => true])
        ->assertSeeHtml('ps-pb-email-meta');

    \Livewire\Livewire::test(PageBuilder::class, ['emailMode' => false])
        ->assertDontSeeHtml('ps-pb-email-meta');
});
