<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Support\ModelFields;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

class ModelFieldsTestWidget extends Model
{
    protected $table = 'mf_widgets';
    protected $guarded = [];
    public $timestamps = false;
}

beforeEach(function () {
    ModelFields::flush();
    Schema::dropIfExists('mf_widgets');
    Schema::create('mf_widgets', function ($t) {
        $t->id();
        $t->string('name');
        $t->integer('qty')->default(0);
        $t->boolean('active')->default(true);
        $t->json('meta')->nullable();
    });
});

it('maps DB columns to page-studio socket types', function () {
    $fields = ModelFields::for(ModelFieldsTestWidget::class);

    expect($fields)->toHaveKey('id')
        ->and($fields['id'])->toBe('int')
        ->and($fields['name'])->toBe('string')
        ->and($fields['qty'])->toBe('int')
        // SQLite blurs boolean → integer storage; either is fine downstream.
        ->and($fields['active'])->toBeIn(['bool', 'int'])
        ->and($fields['meta'])->toBeIn(['array', 'string']);
});

it('returns an empty array for unknown classes', function () {
    expect(ModelFields::for('Acme\\Nope'))->toBe([])
        ->and(ModelFields::for(''))->toBe([]);
});

it('outputsFor expands model_finder outputs when expose_fields is on', function () {
    $pb = new PageBuilder();
    $node = [
        'id' => 'n1',
        'type' => 'source.model_finder',
        'settings' => [
            'model_class'   => ModelFieldsTestWidget::class,
            'finder_key'    => 'id',
            'expose_fields' => true,
        ],
    ];

    $outputs = $pb->outputsFor($node);

    expect(array_keys($outputs))->toEqual(['id', 'name', 'qty', 'active', 'meta'])
        ->and($outputs['active']['type'])->toBeIn(['bool', 'int'])
        ->and($outputs['name']['type'])->toBe('string');
});

it('outputsFor returns the static schema when expose_fields is off', function () {
    $pb = new PageBuilder();
    $node = [
        'id' => 'n1',
        'type' => 'source.model_finder',
        'settings' => ['model_class' => ModelFieldsTestWidget::class],
    ];

    $outputs = $pb->outputsFor($node);

    expect($outputs)->toHaveKey('model')
        ->and($outputs)->not->toHaveKey('name');
});

it('engine emits one output per column when expose_fields is on', function () {
    ModelFieldsTestWidget::create(['id' => 7, 'name' => 'Cog', 'qty' => 42, 'active' => false]);

    $nodes = [
        ['id' => 'k',  'type' => 'source.constant',     'settings' => ['value' => '7']],
        ['id' => 'mf', 'type' => 'source.model_finder', 'settings' => [
            'model_class'   => ModelFieldsTestWidget::class,
            'finder_key'    => 'id',
            'expose_fields' => true,
        ]],
        ['id' => 'o',  'type' => 'output',              'settings' => ['name' => 'widgetName']],
    ];
    $edges = [
        ['from_node' => 'k',  'from_socket' => 'value', 'to_node' => 'mf', 'to_socket' => 'key'],
        ['from_node' => 'mf', 'from_socket' => 'name',  'to_node' => 'o',  'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['widgetName'])->toBe('Cog');
});

it('toggleModelFields flips the per-node flag', function () {
    $route = \LoggedCloud\PageStudio\Models\RouteDefinition::create([
        'name' => 'tmf', 'path_template' => '/tmf', 'method' => 'get',
    ]);
    $pb = new PageBuilder();
    $pb->mount($route->id);
    $pb->nodes = [[
        'id' => 'n1', 'type' => 'source.model_finder', 'position' => ['x' => 0, 'y' => 0],
        'settings' => ['expose_fields' => false],
    ]];

    $pb->toggleModelFields('n1');
    expect($pb->nodes[0]['settings']['expose_fields'])->toBeTrue();

    $pb->toggleModelFields('n1');
    expect($pb->nodes[0]['settings']['expose_fields'])->toBeFalse();
});
