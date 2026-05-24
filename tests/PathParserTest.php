<?php

use LoggedCloud\PageStudio\Support\PathParser;

test('parses a plain path into ordered literal segments', function () {
    $segments = PathParser::parse('/users/123/posts/hello');
    expect($segments)->toHaveCount(4)
        ->and($segments[0])->toMatchArray(['kind' => 'literal', 'value' => 'users'])
        ->and($segments[1])->toMatchArray(['kind' => 'literal', 'value' => '123'])
        ->and($segments[3])->toMatchArray(['kind' => 'literal', 'value' => 'hello']);
});

test('detects {name} placeholders and tags them as variable segments', function () {
    $segments = PathParser::parse('/users/{userId}/posts/{slug}');
    expect($segments[1])->toMatchArray(['kind' => 'variable', 'value' => 'userId'])
        ->and($segments[3])->toMatchArray(['kind' => 'variable', 'value' => 'slug']);
});

test('strips trailing and leading slashes and empty bits', function () {
    expect(PathParser::parse('//users//123/'))->toHaveCount(2);
    expect(PathParser::parse(''))->toBe([]);
    expect(PathParser::parse('/'))->toBe([]);
});

test('compose rebuilds a path from segments and wraps variables', function () {
    $segments = [
        ['kind' => 'literal',  'value' => 'users'],
        ['kind' => 'variable', 'value' => 'userId'],
        ['kind' => 'literal',  'value' => 'posts'],
    ];
    expect(PathParser::compose($segments))->toBe('/users/{userId}/posts');
});

test('compose then parse is a round-trip identity', function () {
    $original = '/teams/{team}/projects/{project}/issues/{id}';
    $segments = PathParser::parse($original);
    expect(PathParser::compose($segments))->toBe($original);
});

test('rejects malformed placeholder names · {123} stays literal', function () {
    $segments = PathParser::parse('/foo/{123}/bar');
    expect($segments[1])->toMatchArray(['kind' => 'literal', 'value' => '{123}']);
});
