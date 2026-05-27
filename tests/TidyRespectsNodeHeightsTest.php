<?php

use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Tidy auto-layout · post-v2.8.0 every node renders its settings on
 * the card, so node heights vary a lot · image.gradient is much
 * taller than source.constant. The pre-refactor tidy() used a flat
 * 130px y-stride and stacked nodes that overlapped or had huge
 * empty gaps in the same column.
 */

it('tidy spaces nodes in the same column by at least the taller node\'s height · no overlaps', function () {
    $pb = new PageBuilder();
    $pb->mount();

    // Two nodes that BFS-resolves to the same column (both are pure
    // sources with no inputs · depth 0). Different schema sizes ·
    // image.gradient has 5 settings, source.constant has 1.
    $pb->nodes = [
        ['id' => 'a', 'type' => 'image.gradient',  'settings' => ['from' => '#000', 'to' => '#fff', 'angle' => 90, 'width' => 200, 'height' => 100], 'position' => ['x' => 0, 'y' => 0]],
        ['id' => 'b', 'type' => 'source.constant', 'settings' => ['value' => 'x'], 'position' => ['x' => 0, 'y' => 0]],
    ];
    $pb->edges = [];

    $pb->tidy();

    $a = collect($pb->nodes)->firstWhere('id', 'a');
    $b = collect($pb->nodes)->firstWhere('id', 'b');

    // Both should land in the SAME column (depth 0 → column 0).
    expect($a['position']['x'])->toBe($b['position']['x']);

    // The bottom of the FIRST node (whichever wins the row-0 slot)
    // must sit ABOVE the top of the SECOND with a real gap. The old
    // tidy() used a 130px stride which puts row-1's top AT row-0's
    // top + 130 · for image.gradient (~50 + 7 rows ≈ 218px tall)
    // that means row-1 would overlap row-0 by ~88px.
    $rows  = [$a, $b];
    usort($rows, fn ($x, $y) => $x['position']['y'] <=> $y['position']['y']);

    [$top, $next] = $rows;
    $topSchema = config('page-studio.nodes')[$top['type']] ?? [];
    $estHeight = 50 +
        24 * (count($topSchema['inputs'] ?? [])
            + count($topSchema['outputs'] ?? [])
            + count($topSchema['settings'] ?? []));

    expect($next['position']['y'])->toBeGreaterThan(
        $top['position']['y'] + $estHeight - 8, // tiny tolerance for rounding
        'next node\'s y must clear the previous node\'s estimated height',
    );
});

it('a short node followed by a tall node also leaves enough space · row spacing is height-driven, not slot-index-driven', function () {
    // Stack three nodes in one column · short / tall / short. Each
    // pair's vertical gap should exceed the upper node's estimated
    // height.
    $pb = new PageBuilder();
    $pb->mount();

    $pb->nodes = [
        ['id' => 'short1', 'type' => 'source.constant',  'settings' => ['value' => '1']],
        ['id' => 'tall',   'type' => 'image.gradient',   'settings' => ['from' => '#fff', 'to' => '#000']],
        ['id' => 'short2', 'type' => 'source.constant',  'settings' => ['value' => '2']],
    ];
    $pb->edges = [];

    $pb->tidy();

    $byId = collect($pb->nodes)->keyBy('id');

    // Sort by y, walk through, assert each row clears the prior.
    $sorted = $byId->sortBy(fn ($n) => $n['position']['y'])->values();
    for ($i = 0; $i < $sorted->count() - 1; $i++) {
        $upper = $sorted[$i];
        $lower = $sorted[$i + 1];
        $schema = config('page-studio.nodes')[$upper['type']] ?? [];
        $estH = 50 + 24 * (count($schema['inputs'] ?? []) + count($schema['outputs'] ?? []) + count($schema['settings'] ?? []));
        expect($lower['position']['y'])->toBeGreaterThan(
            $upper['position']['y'] + $estH - 8,
            "row $i ({$upper['type']}, ~{$estH}px) should not overlap row ".($i + 1)." ({$lower['type']})",
        );
    }
});
