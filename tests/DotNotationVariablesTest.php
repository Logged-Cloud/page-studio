<?php

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Support\PageRenderer;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class DotVarTestUser extends Model
{
    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'noop_users';
}

it('exposes a root entry + a dot-path entry for each Eloquent attribute', function () {
    $pb = new PageBuilder();
    $user = new DotVarTestUser(['id' => 42, 'name' => 'Charles', 'email' => 'c@example.com']);

    $pb->mount(null, null, ['user' => $user]);

    $names = collect($pb->variables)->pluck('name')->all();
    expect($names)->toContain('user', 'user.id', 'user.name', 'user.email');

    $emailEntry = collect($pb->variables)->firstWhere('name', 'user.email');
    expect($emailEntry['preview'])->toBe('c@example.com')
        ->and($emailEntry['label'])->toBe('email');
});

it('uses the models name attribute as the root preview', function () {
    $pb = new PageBuilder();
    $user = new DotVarTestUser(['id' => 7, 'name' => 'Charles', 'email' => 'c@x']);

    $pb->mount(null, null, ['user' => $user]);

    $root = collect($pb->variables)->firstWhere('name', 'user');
    expect($root['preview'])->toBe('Charles');
});

it('flattens plain arrays into dot-path leaves', function () {
    $pb = new PageBuilder();
    $pb->mount(null, null, [
        'config' => ['from_name' => 'MBR', 'reply_to' => 'support@example.com'],
    ]);

    $names = collect($pb->variables)->pluck('name')->all();
    expect($names)->toContain('config', 'config.from_name', 'config.reply_to');

    $entry = collect($pb->variables)->firstWhere('name', 'config.from_name');
    expect($entry['preview'])->toBe('MBR');
});

it('renderText substitutes dot-path tokens against a flat-key context', function () {
    $html = PageRenderer::renderText(
        'Hello {{ user.name }} at {{ user.email }}',
        ['user.name' => 'Charles', 'user.email' => 'c@example.com'],
        false,
    );
    expect($html)->toContain('Hello Charles at c@example.com');
});

it('renderText substitutes dot-path tokens against a nested context', function () {
    $html = PageRenderer::renderText(
        'Hello {{ user.name }}',
        ['user' => ['name' => 'Charles']],
        false,
    );
    expect($html)->toContain('Hello Charles');
});

it('leaves unsubstituted dot tokens untouched when the path is missing', function () {
    $html = PageRenderer::renderText('Hi {{ user.email }}', [], false);
    expect($html)->toContain('{{ user.email }}');
});

it('substitute() honours dotted tokens for non-decorate attribute contexts', function () {
    $rendered = PageRenderer::substitute('mailto:{{ user.email }}', ['user' => ['email' => 'c@x.com']]);
    expect($rendered)->toBe('mailto:c@x.com');
});

it('renderText escapes the substituted value (XSS hygiene)', function () {
    $html = PageRenderer::renderText(
        'Hi {{ user.name }}',
        ['user.name' => '<script>alert(1)</script>'],
        false,
    );
    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});
