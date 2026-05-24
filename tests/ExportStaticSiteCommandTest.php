<?php

use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Build a temp directory we can hand to --out · the suite must not pollute
 * storage/app/page-studio/. Cleaned up in afterEach so a failure halfway
 * through still leaves a tidy filesystem.
 */
function ess_tempDir(): string
{
    $dir = sys_get_temp_dir().'/page-studio-export-'.uniqid();
    mkdir($dir, 0775, true);
    return $dir;
}

function ess_rrmdir(string $dir): void
{
    if (! is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir.'/'.$entry;
        is_dir($path) ? ess_rrmdir($path) : unlink($path);
    }
    rmdir($dir);
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/page-studio-export-*') ?: [] as $dir) {
        ess_rrmdir($dir);
    }
});

function ess_seedRoute(string $name, string $heading): RouteDefinition
{
    $route = RouteDefinition::create([
        'name'          => $name,
        'path_template' => '/'.str_replace('.', '/', $name),
        'method'        => 'get',
    ]);
    Page::create([
        'route_id' => $route->id,
        'blocks'   => [
            ['type' => 'heading',   'settings' => ['text' => $heading, 'level' => 'h1', 'align' => 'left']],
            ['type' => 'paragraph', 'settings' => ['text' => 'Body for '.$heading]],
        ],
        'meta'     => [],
    ]);
    return $route;
}

it('writes one html file per saved route into the picked output directory', function () {
    ess_seedRoute('first',  'First');
    ess_seedRoute('second', 'Second');
    ess_seedRoute('third',  'Third');

    $out = ess_tempDir();
    \Illuminate\Support\Facades\Artisan::call('page-studio:export-static-site', ['--out' => $out]);

    $files = glob($out.'/*.html') ?: [];
    expect(count($files))->toBe(3);
});

it('each rendered file contains the doctype wrapper and the rendered blocks', function () {
    ess_seedRoute('first', 'Hello world');

    $out = ess_tempDir();
    \Illuminate\Support\Facades\Artisan::call('page-studio:export-static-site', ['--out' => $out]);

    $html = (string) file_get_contents($out.'/first.html');
    expect($html)->toContain('<!doctype html>')
        ->and($html)->toContain('<main>')
        ->and($html)->toContain('Hello world')
        ->and($html)->toContain('Body for Hello world');
});

it('sanitises dots in route names to hyphens for the filename', function () {
    ess_seedRoute('users.show', 'User profile');

    $out = ess_tempDir();
    \Illuminate\Support\Facades\Artisan::call('page-studio:export-static-site', ['--out' => $out]);

    expect(file_exists($out.'/users-show.html'))->toBeTrue()
        ->and(file_exists($out.'/users.show.html'))->toBeFalse();
});

it('honours --out when provided and falls back to storage/app/page-studio/static otherwise', function () {
    ess_seedRoute('fallback', 'Default location');

    // Override -> custom directory.
    $out = ess_tempDir();
    \Illuminate\Support\Facades\Artisan::call('page-studio:export-static-site', ['--out' => $out]);
    expect(file_exists($out.'/fallback.html'))->toBeTrue();

    // No --out -> default storage path.
    $default = storage_path('app/page-studio/static');
    // Clean any leftover from a previous run before we exercise the default.
    if (is_dir($default)) {
        foreach (glob($default.'/*.html') ?: [] as $f) @unlink($f);
    }
    \Illuminate\Support\Facades\Artisan::call('page-studio:export-static-site');
    expect(file_exists($default.'/fallback.html'))->toBeTrue();

    // Tidy up the default location so we don't pollute storage/.
    @unlink($default.'/fallback.html');
});
