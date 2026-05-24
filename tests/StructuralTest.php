<?php

/*
| Static checks · ensures the package shape stays stable: service provider
| wiring, config keys, Livewire components, Blade view existence. Mirrors the
| input package's structural-test approach so the suite stays sub-second.
*/

$provider = __DIR__.'/../src/PageStudioServiceProvider.php';
$config   = __DIR__.'/../config/page-studio.php';
$builderBlade = __DIR__.'/../resources/views/livewire/route-builder.blade.php';
$libraryBlade = __DIR__.'/../resources/views/livewire/variable-library.blade.php';
$routeBuilder = __DIR__.'/../src/Livewire/RouteBuilder.php';

test('service provider loads views, config, migrations', function () use ($provider) {
    $src = file_get_contents($provider);
    expect($src)
        ->toContain("loadViewsFrom(__DIR__.'/../resources/views', 'page-studio')")
        ->and($src)->toContain('loadMigrationsFrom')
        ->and($src)->toContain('page-studio-config')
        ->and($src)->toContain('page-studio-views')
        ->and($src)->toContain('page-studio-migrations');
});

test('service provider registers Livewire components only when Livewire is present', function () use ($provider) {
    $src = file_get_contents($provider);
    expect($src)
        ->toContain('class_exists(\\Livewire\\Livewire::class)')
        ->and($src)->toContain("\$this->app->bound('livewire')")
        ->and($src)->toContain("'page-studio.route-builder'")
        ->and($src)->toContain("'page-studio.variable-library'");
});

test('config defines the seven built-in variable types', function () use ($config) {
    $cfg = include $config;
    expect($cfg['variable_types'])->toHaveKeys(['int', 'slug', 'uuid', 'alpha', 'enum', 'any', 'custom']);
    foreach (['int', 'slug', 'uuid', 'alpha', 'any'] as $type) {
        expect($cfg['variable_types'][$type])->toHaveKeys(['label', 'where', 'validate', 'example']);
        expect($cfg['variable_types'][$type]['where'])->not->toBeEmpty();
    }
});

test('config requires at least three examples per variable by default', function () use ($config) {
    $cfg = include $config;
    expect($cfg['min_examples_per_variable'])->toBe(3);
});

test('config exposes a table_prefix knob so storage can be namespaced', function () use ($config) {
    $cfg = include $config;
    expect($cfg)->toHaveKey('table_prefix')
        ->and($cfg['table_prefix'])->toBe('page_studio_');
});

test('route-builder blade renders a plain path input bound to rawPath', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->toContain('class="ps-rb-path-input"')
        ->and($tpl)->toContain('wire:model.live.debounce.300ms="rawPath"')
        ->and($tpl)->toContain('class="ps-rb-path-prefix"');
});

test('route-builder blade does NOT expose a method dropdown · GET is implicit', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->not->toContain('wire:model.live="method"')
        ->not->toContain('Method</label>');
});

test('route-builder blade renders parsed segments as left+right-clickable chips', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->toContain('x-for="(seg, i) in segments"')
        ->and($tpl)->toContain('class="ps-rb-chip"')
        ->and($tpl)->toContain(':class="seg.kind === \'variable\' ? \'is-variable\' : \'is-literal\'"')
        // Left-click and right-click both pop the chip's menu · easier to use,
        // especially since the chips live in their own row.
        ->and($tpl)->toContain('@click.stop="toggleMenu(i)"')
        ->and($tpl)->toContain('@contextmenu.prevent.stop="toggleMenu(i)"');
});

test('route-builder blade renders a per-chip popover menu (no cursor coords)', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    // Each chip wraps its menu in a relative ps-rb-chip-pop so the menu
    // anchors right below the chip · no absolute clientX/Y math.
    expect($tpl)
        ->toContain('class="ps-rb-chip-pop"')
        ->and($tpl)->toContain('x-show="openMenuIndex === i"');
});

test('route-builder blade wires a long-press fallback for touch devices', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->toContain('@touchstart="onTouchStart(i, $event)"')
        ->and($tpl)->toContain('@touchend="onTouchEnd()"');
});

test('route-builder blade exposes context-menu actions for both literal and variable chips', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->toContain('turnIntoVariable(i)')
        ->and($tpl)->toContain('turnIntoLiteral(i)')
        ->and($tpl)->toContain('editVariable(i)')
        ->and($tpl)->toContain('removeSegment(i)');
});

test('route-builder blade renders the variable-definition panel with examples + type', function () use ($builderBlade) {
    $tpl = file_get_contents($builderBlade);
    expect($tpl)
        ->toContain('wire:model.live="newVariable.name"')
        ->and($tpl)->toContain('wire:model.live="newVariable.type"')
        ->and($tpl)->toContain('wire:model.live="newVariable.examples')
        ->and($tpl)->toContain('wire:click="commitVariable"');
});

test('route-builder PHP re-parses the rawPath into segments on every update', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)
        ->toContain('public string $rawPath')
        ->and($src)->toContain('updatedRawPath')
        ->and($src)->toContain('$this->applyRawPath($this->rawPath)');
});

test('route-builder PHP strips the leading slash for the input · the prefix span already renders one', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)
        ->toContain('protected function segmentsToInputPath')
        ->and($src)->toContain("ltrim(\$full, '/')");
});

test('route-builder PHP hard-codes method=GET on save · page builder is GET-only', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)
        ->toContain("'method'        => 'GET'");
});

test('route-builder PHP enforces variable-name shape and minimum-examples count', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)
        ->toContain("regex:/^[A-Za-z_][A-Za-z0-9_]*$/")
        ->and($src)->toContain('min_examples_per_variable')
        ->and($src)->toContain('at least');
});

test('route-builder PHP validates examples against the variable rule', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)
        ->toContain('collectExampleMismatches')
        ->and($src)->toContain('whereConstraint()')
        ->and($src)->toContain('does not match the variable');
});

test('route-builder PHP refuses to save a custom-type variable without a regex', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    expect($src)->toContain("'custom' && trim((string) \$data['regex']) === ''");
});

test('route-builder PHP keeps rawPath and segments in sync after segment edits', function () use ($routeBuilder) {
    $src = file_get_contents($routeBuilder);
    // Every mutation that changes the segment array also refreshes the rawPath
    // via segmentsToInputPath() (leading slash stripped).
    expect($src)
        ->toContain('$this->rawPath = $this->segmentsToInputPath()')
        ->and($src)->toContain('protected function segmentsToPath()');
});

test('variable library renders a search-filtered table with in-use protection', function () use ($libraryBlade) {
    $tpl = file_get_contents($libraryBlade);
    expect($tpl)
        ->toContain('wire:model.live.debounce.250ms="search"')
        ->and($tpl)->toContain('@disabled($variable->segments_count > 0)')
        ->and($tpl)->toContain('wire:click="delete(');
});
