<?php

use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

beforeEach(function () {
    $this->tpl = file_get_contents(__DIR__.'/../resources/views/livewire/page-builder.blade.php');
});

test('icon-only node action buttons carry an aria-label', function () {
    expect($this->tpl)
        ->toContain('aria-label="Mute node"')
        ->toContain('aria-label="Expose fields as outputs"')
        ->toContain('aria-label="Duplicate node"')
        ->toContain('aria-label="Delete node"');
});

test('toggle buttons announce their pressed state via aria-pressed', function () {
    expect($this->tpl)
        ->toContain('aria-pressed="{{ ! empty($node[\'muted\']) ? \'true\' : \'false\' }}"')
        ->toContain('aria-pressed="{{ ! empty($node[\'settings\'][\'expose_fields\']) ? \'true\' : \'false\' }}"')
        ->toContain(":aria-pressed=\"leftCollapsed ? 'true' : 'false'\"")
        ->toContain(":aria-pressed=\"rightCollapsed ? 'true' : 'false'\"");
});

test('rail toggle buttons carry a dynamic aria-label', function () {
    expect($this->tpl)
        ->toContain(":aria-label=\"leftCollapsed ? 'Show components rail' : 'Hide components rail'\"")
        ->toContain(":aria-label=\"rightCollapsed ? 'Show settings rail' : 'Hide settings rail'\"");
});

test('icon-only glyphs inside toggle buttons are hidden from assistive tech', function () {
    expect(substr_count($this->tpl, 'aria-hidden="true"'))->toBeGreaterThanOrEqual(4);
});

test('keyboard-shortcuts trigger button carries an aria-label', function () {
    expect($this->tpl)->toContain('aria-label="Show keyboard shortcuts"');
});
