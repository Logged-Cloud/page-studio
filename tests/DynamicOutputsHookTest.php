<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Nodes\NodeRegistry;
use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class CsvHeadersNode extends NodeType
{
    public static function key(): string   { return 'custom.csv_headers'; }
    public static function label(): string { return 'CSV headers'; }
    public static function group(): string { return 'source'; }

    public static function outputs(): array { return ['rows' => ['label' => 'Rows', 'type' => 'array']]; }

    public static function settings(): array
    {
        return [
            'columns' => ['kind' => 'text', 'label' => 'Comma-separated columns', 'default' => ''],
        ];
    }

    public function dynamicOutputs(array $node): ?array
    {
        $cols = array_filter(array_map('trim', explode(',', (string) ($node['settings']['columns'] ?? ''))));
        if (empty($cols)) return null;

        $outputs = [];
        foreach ($cols as $col) {
            $outputs[$col] = ['label' => $col, 'type' => 'string'];
        }
        return $outputs;
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return [];
    }
}

beforeEach(function () {
    NodeRegistry::clear();
    NodeRegistry::register(CsvHeadersNode::class);
    config()->set('page-studio.nodes', [
        'custom.csv_headers' => CsvHeadersNode::toLibraryEntry(),
    ]);
});

it('a custom NodeType with dynamicOutputs replaces the canvas socket list', function () {
    $pb = new PageBuilder();

    $outputs = $pb->outputsFor([
        'id'   => 'c1',
        'type' => 'custom.csv_headers',
        'settings' => ['columns' => 'first_name, last_name, email'],
    ]);

    expect(array_keys($outputs))->toEqual(['first_name', 'last_name', 'email'])
        ->and($outputs['email']['type'])->toBe('string');
});

it('returning null from dynamicOutputs keeps the static schema', function () {
    $pb = new PageBuilder();

    $outputs = $pb->outputsFor([
        'id'   => 'c1',
        'type' => 'custom.csv_headers',
        'settings' => [],
    ]);

    expect($outputs)->toHaveKey('rows')
        ->and($outputs)->not->toHaveKey('first_name');
});

it('a thrown exception inside dynamicOutputs falls back to the static schema, not a crash', function () {
    NodeRegistry::clear();
    NodeRegistry::register(BrokenDynamicOutputsNode::class);
    config()->set('page-studio.nodes', [
        'custom.broken_dynamic' => BrokenDynamicOutputsNode::toLibraryEntry(),
    ]);

    $pb = new PageBuilder();
    $outputs = $pb->outputsFor([
        'id' => 'b1',
        'type' => 'custom.broken_dynamic',
        'settings' => [],
    ]);

    expect($outputs)->toHaveKey('value');
});

class BrokenDynamicOutputsNode extends NodeType
{
    public static function key(): string   { return 'custom.broken_dynamic'; }
    public static function label(): string { return 'Broken'; }

    public function dynamicOutputs(array $node): ?array
    {
        throw new \RuntimeException('boom');
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return [];
    }
}
