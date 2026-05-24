<?php

use LoggedCloud\PageStudio\Support\TypeExamples;

test('uuid examples are real RFC 4122 UUIDs', function () {
    $examples = TypeExamples::for('uuid');
    expect($examples)->toHaveCount(3);
    foreach ($examples as $uuid) {
        expect($uuid)->toMatch('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/');
    }
});

test('int examples are all integers', function () {
    foreach (TypeExamples::for('int') as $v) {
        expect($v)->toMatch('/^\d+$/');
    }
});

test('slug examples match the slug where-constraint', function () {
    $where = '[a-z0-9](-?[a-z0-9])*';
    foreach (TypeExamples::for('slug') as $v) {
        expect((bool) preg_match("/^$where$/", $v))->toBeTrue("Failed for: $v");
    }
});

test('alpha examples are all letters', function () {
    foreach (TypeExamples::for('alpha') as $v) {
        expect($v)->toMatch('/^[A-Za-z]+$/');
    }
});

test('enum + custom return empty because they are user-defined', function () {
    expect(TypeExamples::for('enum'))->toBe([]);
    expect(TypeExamples::for('custom'))->toBe([]);
});

test('every built-in type with a `where` constraint has at least 3 stock examples', function () {
    $config = include __DIR__.'/../config/page-studio.php';
    foreach ($config['variable_types'] as $type => $cfg) {
        if (! ($cfg['where'] ?? null)) continue;
        $examples = TypeExamples::for($type);
        expect($examples)->toHaveCount(3, "Type {$type} should have 3 examples, got ".count($examples));
    }
});
