<?php

use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

test('make seeds settings from the type schema defaults', function () {
    $block = BlockFactory::make('heading');
    expect($block)->toHaveKeys(['id', 'type', 'settings'])
        ->and($block['type'])->toBe('heading')
        ->and($block['settings']['text'])->toBe('Section heading')
        ->and($block['settings']['level'])->toBe('h2');
});

test('make returns null for an unknown type', function () {
    expect(BlockFactory::make('not-a-real-block'))->toBeNull();
});

test('make generates a unique id per block', function () {
    $a = BlockFactory::make('paragraph');
    $b = BlockFactory::make('paragraph');
    expect($a['id'])->not->toBe($b['id']);
});

test('sanitiseAll drops blocks of removed types', function () {
    $clean = BlockFactory::sanitiseAll([
        ['id' => '1', 'type' => 'heading',  'settings' => ['text' => 'X']],
        ['id' => '2', 'type' => 'phantom',  'settings' => []],
        ['id' => '3', 'type' => 'paragraph', 'settings' => ['text' => 'Y']],
    ]);
    expect($clean)->toHaveCount(2);
    expect(array_column($clean, 'type'))->toBe(['heading', 'paragraph']);
});

test('sanitiseAll fills in missing settings with defaults', function () {
    $clean = BlockFactory::sanitiseAll([
        ['id' => 'a', 'type' => 'heading', 'settings' => []],
    ]);
    expect($clean[0]['settings']['text'])->toBe('Section heading')
        ->and($clean[0]['settings']['level'])->toBe('h2')
        ->and($clean[0]['settings']['align'])->toBe('left');
});

test('sanitiseAll strips unknown setting keys', function () {
    $clean = BlockFactory::sanitiseAll([
        ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'X', 'evil' => '<script>']],
    ]);
    expect($clean[0]['settings'])->not->toHaveKey('evil');
});
