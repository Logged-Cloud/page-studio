<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Support\RouteCompiler;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in(__FILE__);

test('compile assembles template + where + examples for a mixed route', function () {
    $var = Variable::create([
        'name'     => 'userId',
        'type'     => 'int',
        'examples' => ['1', '2', '3'],
    ]);

    $route = RouteDefinition::create([
        'name'          => 'users.show',
        'method'        => 'GET',
        'path_template' => '/users/{userId}',
    ]);
    $route->segments()->create(['position' => 0, 'kind' => 'literal',  'literal_value' => 'users']);
    $route->segments()->create(['position' => 1, 'kind' => 'variable', 'variable_id'   => $var->id]);

    $compiled = RouteCompiler::compile($route->fresh('segments.variable'));

    expect($compiled['method'])->toBe('GET')
        ->and($compiled['template'])->toBe('/users/{userId}')
        ->and($compiled['where'])->toBe(['userId' => '[0-9]+'])
        ->and($compiled['examples'])->toContain('/users/1')
        ->and($compiled['examples'])->toContain('/users/2')
        ->and($compiled['examples'])->toContain('/users/3');
});

test('compile caps the cartesian example expansion', function () {
    $a = Variable::create(['name' => 'a', 'type' => 'enum', 'examples' => ['a1','a2','a3','a4','a5']]);
    $b = Variable::create(['name' => 'b', 'type' => 'enum', 'examples' => ['b1','b2','b3','b4','b5']]);

    $route = RouteDefinition::create([
        'name' => 'demo', 'method' => 'GET', 'path_template' => '/{a}/{b}',
    ]);
    $route->segments()->create(['position' => 0, 'kind' => 'variable', 'variable_id' => $a->id]);
    $route->segments()->create(['position' => 1, 'kind' => 'variable', 'variable_id' => $b->id]);

    $compiled = RouteCompiler::compile($route->fresh('segments.variable'), exampleCap: 6);
    expect(count($compiled['examples']))->toBeLessThanOrEqual(6);
});

test('compile sets a where constraint per variable with a defined rule', function () {
    $var = Variable::create([
        'name' => 'slug', 'type' => 'slug', 'examples' => ['a', 'b', 'c'],
    ]);
    $route = RouteDefinition::create([
        'name' => 'posts.show', 'method' => 'GET', 'path_template' => '/posts/{slug}',
    ]);
    $route->segments()->create(['position' => 0, 'kind' => 'literal',  'literal_value' => 'posts']);
    $route->segments()->create(['position' => 1, 'kind' => 'variable', 'variable_id'   => $var->id]);

    $compiled = RouteCompiler::compile($route->fresh('segments.variable'));

    expect($compiled['where'])->toHaveKey('slug')
        ->and($compiled['where']['slug'])->toBe('[a-z0-9](-?[a-z0-9])*');
});
