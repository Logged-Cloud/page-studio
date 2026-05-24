<?php

use Illuminate\Support\Facades\Artisan;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Templates\Builtin\BlogPostTemplate;
use LoggedCloud\PageStudio\Templates\Builtin\LandingTemplate;
use LoggedCloud\PageStudio\Templates\Builtin\UserProfileTemplate;
use LoggedCloud\PageStudio\Templates\Template;
use LoggedCloud\PageStudio\Templates\TemplateRegistry;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class DummyTestTemplate extends Template
{
    public static function name(): string  { return 'dummy'; }
    public static function label(): string { return 'Dummy'; }
    public static function route(): array
    {
        return [
            'name' => 'dummy.show', 'method' => 'GET', 'path_template' => '/dummy',
            'segments' => [['position' => 0, 'kind' => 'literal', 'literal_value' => 'dummy']],
        ];
    }
}

it('TemplateRegistry registers, finds, and lists templates', function () {
    TemplateRegistry::register(DummyTestTemplate::class);
    expect(TemplateRegistry::find('dummy'))->toBe(DummyTestTemplate::class)
        ->and(TemplateRegistry::all())->toHaveKey('dummy');
});

it('TemplateRegistry refuses non-Template classes', function () {
    TemplateRegistry::register(\stdClass::class);
})->throws(InvalidArgumentException::class);

it('the built-in templates expose the expected slugs', function () {
    expect(BlogPostTemplate::name())->toBe('blog-post')
        ->and(UserProfileTemplate::name())->toBe('user-profile')
        ->and(LandingTemplate::name())->toBe('landing');
});

it('install-template creates the route + variables + page from a template', function () {
    TemplateRegistry::register(BlogPostTemplate::class);

    Artisan::call('page-studio:install-template', ['name' => 'blog-post']);

    $route = RouteDefinition::where('name', 'blog.show')->first();
    expect($route)->not->toBeNull()
        ->and($route->path_template)->toBe('/blog/{slug}')
        ->and(Variable::where('name', 'slug')->exists())->toBeTrue()
        ->and(Page::where('route_id', $route->id)->exists())->toBeTrue();
});

it('install-template builds the user-profile graph too', function () {
    TemplateRegistry::register(UserProfileTemplate::class);

    Artisan::call('page-studio:install-template', ['name' => 'user-profile']);

    $route = RouteDefinition::where('name', 'users.show')->first();
    $graph = NodeGraph::where('route_id', $route->id)->first();
    expect($graph)->not->toBeNull()
        ->and(collect($graph->nodes)->pluck('type'))->toContain('source.model_finder')
        ->and(collect($graph->edges))->toHaveCount(3);
});

it('install-template honours --rename', function () {
    TemplateRegistry::register(BlogPostTemplate::class);

    Artisan::call('page-studio:install-template', ['name' => 'blog-post', '--rename' => 'posts.show']);

    expect(RouteDefinition::where('name', 'posts.show')->exists())->toBeTrue();
});

it('install-template lists available templates when no name is given', function () {
    TemplateRegistry::register(BlogPostTemplate::class);
    TemplateRegistry::register(LandingTemplate::class);

    $exit = Artisan::call('page-studio:install-template');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('blog-post')
        ->and($output)->toContain('landing');
});
