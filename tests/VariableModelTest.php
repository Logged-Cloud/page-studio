<?php

use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

test('whereConstraint returns the config regex for built-in types', function () {
    $var = new Variable();
    $var->type = 'int';
    expect($var->whereConstraint())->toBe('[0-9]+');

    $var->type = 'slug';
    expect($var->whereConstraint())->toBe('[a-z0-9](-?[a-z0-9])*');

    $var->type = 'uuid';
    expect($var->whereConstraint())->toBe('[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');
});

test('whereConstraint for enum is built from preg_quoted examples', function () {
    $var = new Variable();
    $var->type = 'enum';
    $var->examples = ['draft', 'published', 'archived'];
    expect($var->whereConstraint())->toBe('(draft|published|archived)');
});

test('whereConstraint for enum preg-quotes special chars in examples', function () {
    $var = new Variable();
    $var->type = 'enum';
    $var->examples = ['a.b', 'c+d'];
    expect($var->whereConstraint())->toBe('(a\.b|c\+d)');
});

test('whereConstraint for enum with empty examples returns null', function () {
    $var = new Variable();
    $var->type = 'enum';
    $var->examples = [];
    expect($var->whereConstraint())->toBeNull();
});

test('whereConstraint for custom returns the supplied regex', function () {
    $var = new Variable();
    $var->type = 'custom';
    $var->regex = '[a-z]{3}-\d+';
    expect($var->whereConstraint())->toBe('[a-z]{3}-\d+');
});

test('whereConstraint for custom with empty regex returns null', function () {
    $var = new Variable();
    $var->type = 'custom';
    $var->regex = null;
    expect($var->whereConstraint())->toBeNull();
});

test('validationRule for enum returns Laravel-style in: rule', function () {
    $var = new Variable();
    $var->type = 'enum';
    $var->examples = ['draft', 'published'];
    expect($var->validationRule())->toBe('in:draft,published');
});

test('validationRule for custom wraps the regex in anchors', function () {
    $var = new Variable();
    $var->type = 'custom';
    $var->regex = '[a-z]+';
    expect($var->validationRule())->toBe('regex:/^[a-z]+$/');
});

test('validationRule for built-in types comes from config', function () {
    $var = new Variable();
    $var->type = 'uuid';
    expect($var->validationRule())->toBe('uuid');

    $var->type = 'int';
    expect($var->validationRule())->toBe('integer');
});
