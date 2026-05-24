<?php

use LoggedCloud\PageStudio\Livewire\RouteBuilder;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

it('collectExampleMismatches flags uuid examples that fail the regex', function () {
    $rb = new RouteBuilder();
    $v = new Variable([
        'type'     => 'uuid',
        'examples' => ['550e8400-e29b-41d4-a716-446655440000', '002asdasd', 'not-a-uuid-either'],
    ]);
    $bad = $rb->collectExampleMismatches($v);

    expect($bad)->toHaveCount(2)
        ->and($bad)->toHaveKey(1)
        ->and($bad)->toHaveKey(2)
        ->and($bad[1])->toContain('002asdasd');
});

it('collectExampleMismatches passes a clean uuid set', function () {
    $rb = new RouteBuilder();
    $v = new Variable([
        'type'     => 'uuid',
        'examples' => [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            '00112233-4455-6677-8899-aabbccddeeff',
        ],
    ]);
    expect($rb->collectExampleMismatches($v))->toBe([]);
});

it('collectExampleMismatches returns empty for types with no regex (any)', function () {
    $rb = new RouteBuilder();
    $v = new Variable(['type' => 'any', 'examples' => ['anything-goes', '0', '!@#']]);
    expect($rb->collectExampleMismatches($v))->toBe([]);
});

it('collectExampleMismatches rejects ints that contain letters', function () {
    $rb = new RouteBuilder();
    $v = new Variable(['type' => 'int', 'examples' => ['1', 'abc', '42']]);
    $bad = $rb->collectExampleMismatches($v);
    expect($bad)->toHaveKey(1);
});

it('commitVariable does NOT persist a uuid var with bad examples', function () {
    \Livewire\Livewire::test(RouteBuilder::class)
        ->set('newVariable', [
            'name'        => 'bad_uuid_example',
            'label'       => '',
            'type'        => 'uuid',
            'regex'       => null,
            'description' => '',
            'examples'    => ['550e8400-e29b-41d4-a716-446655440000', 'not-a-uuid', 'also-bad'],
        ])
        ->call('commitVariable');

    expect(Variable::where('name', 'bad_uuid_example')->exists())->toBeFalse();
});

it('commitVariable persists when every example matches the regex', function () {
    \Livewire\Livewire::test(RouteBuilder::class)
        ->set('newVariable', [
            'name'        => 'good_uuid_example',
            'label'       => '',
            'type'        => 'uuid',
            'regex'       => null,
            'description' => '',
            'examples'    => [
                '550e8400-e29b-41d4-a716-446655440000',
                '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
                '00112233-4455-6677-8899-aabbccddeeff',
            ],
        ])
        ->call('commitVariable');

    expect(Variable::where('name', 'good_uuid_example')->exists())->toBeTrue();
});

it('collectExampleMismatches honours a custom regex from the variable row', function () {
    $rb = new RouteBuilder();
    $v = new Variable([
        'type'     => 'custom',
        'regex'    => '[a-z]{3}',
        'examples' => ['abc', 'xyz', 'NOPE'],
    ]);
    $bad = $rb->collectExampleMismatches($v);
    expect($bad)->toHaveKey(2)
        ->and($bad)->not->toHaveKey(0);
});
