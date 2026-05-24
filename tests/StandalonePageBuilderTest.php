<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('mounts in ephemeral mode with no routeId + no pageId', function () {
    $pb = new PageBuilder();
    $pb->mount();
    expect($pb->routeId)->toBeNull()
        ->and($pb->pageId)->toBeNull()
        ->and($pb->blocks)->toBe([])
        ->and($pb->variables)->toBe([]);
});

it('mounts with custom variables in the flat shape and surfaces them as palette chips', function () {
    $pb = new PageBuilder();
    $pb->mount(null, null, [
        'campaign_name' => 'Summer 2026',
        'client_email'  => 'foo@bar.com',
    ]);

    $names = collect($pb->variables)->pluck('name')->all();
    expect($names)->toEqual(['campaign_name', 'client_email'])
        ->and($pb->variables[0]['preview'])->toBe('Summer 2026')
        ->and($pb->variables[0]['source'])->toBe('caller');
});

it('mounts with custom variables in the richer label form', function () {
    $pb = new PageBuilder();
    $pb->mount(null, null, [
        ['name' => 'campaign_name', 'label' => 'Campaign', 'preview' => 'Summer 2026'],
    ]);

    expect($pb->variables[0])->toMatchArray([
        'name'    => 'campaign_name',
        'label'   => 'Campaign',
        'preview' => 'Summer 2026',
        'source'  => 'caller',
    ]);
});

it('save() is a no-op in ephemeral mode but emits the saved event', function () {
    $pb = \Livewire\Livewire::test(PageBuilder::class)
        ->call('save');

    expect(Page::count())->toBe(0);
    $pb->assertDispatched('page-studio:page:saved');
});

it('binds to a specific Page via pageId without needing a routeId', function () {
    $page = Page::create(['route_id' => null, 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'Stored copy']]]]);

    $pb = new PageBuilder();
    $pb->mount(null, $page->id);

    expect($pb->pageId)->toBe($page->id)
        ->and($pb->blocks[0]['settings']['text'])->toBe('Stored copy');
});

it('routeContext merges caller-supplied variables under their declared names', function () {
    $pb = new PageBuilder();
    $pb->mount(null, null, ['campaign_name' => 'Summer']);

    // Use reflection to reach the protected method.
    $ref = new ReflectionMethod($pb, 'routeContext');
    $ref->setAccessible(true);
    $ctx = $ref->invoke($pb);

    expect($ctx)->toBe(['campaign_name' => 'Summer']);
});

it('caller-supplied variable wins over a route segment of the same name', function () {
    $route = RouteDefinition::create(['name' => 'rsh', 'path_template' => '/foo/{campaign_name}', 'method' => 'get']);
    \LoggedCloud\PageStudio\Models\RouteSegment::create([
        'route_id' => $route->id, 'position' => 0, 'kind' => 'literal', 'literal_value' => 'foo',
    ]);
    $v = \LoggedCloud\PageStudio\Models\Variable::create([
        'name' => 'campaign_name', 'type' => 'any', 'examples' => ['from-route'],
    ]);
    \LoggedCloud\PageStudio\Models\RouteSegment::create([
        'route_id' => $route->id, 'position' => 1, 'kind' => 'variable', 'variable_id' => $v->id,
    ]);

    $pb = new PageBuilder();
    $pb->mount($route->id, null, ['campaign_name' => 'Summer from caller']);

    $matching = collect($pb->variables)->where('name', 'campaign_name')->first();
    expect($matching['preview'])->toBe('Summer from caller')
        ->and($matching['source'])->toBe('caller');
});
