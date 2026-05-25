<?php

/**
 * Synthetic render benchmark · drop a fat block tree through PageRenderer
 * and report wall-clock time + per-block average. Useful to compare the
 * before / after of any perf change to the render path.
 *
 *     docker run --rm -v $PWD:/work -w /work php:8.4-cli \
 *         php bench/render.php
 */

require __DIR__.'/../vendor/autoload.php';

use Orchestra\Testbench\Foundation\Bootstrap\LoadConfiguration;

$app = new Illuminate\Foundation\Application(realpath(__DIR__.'/..'));
$app->singleton(Illuminate\Contracts\Http\Kernel::class, Illuminate\Foundation\Http\Kernel::class);
$app->singleton(Illuminate\Contracts\Console\Kernel::class, Illuminate\Foundation\Console\Kernel::class);
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, Illuminate\Foundation\Exceptions\Handler::class);

// Minimal config so the package's BlockRegistry is populated.
$app->bind('config', function () {
    return new Illuminate\Config\Repository([
        'page-studio' => require __DIR__.'/../config/page-studio.php',
        'cache'       => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        'app'         => ['debug' => false],
        'view'        => ['paths' => [], 'compiled' => sys_get_temp_dir()],
    ]);
});
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

(new LoggedCloud\PageStudio\PageStudioServiceProvider($app))->register();
(new LoggedCloud\PageStudio\PageStudioServiceProvider($app))->boot();

function makeBlock(int $i): array
{
    return [
        ['id' => "h-$i", 'type' => 'heading',   'settings' => ['text' => "Section $i: {{ name }}", 'level' => 'h2', 'align' => 'left']],
        ['id' => "p-$i", 'type' => 'paragraph', 'settings' => ['text' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit. {{ name }} sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Quote: {{ quote }}."]],
        ['id' => "b-$i", 'type' => 'button',    'settings' => ['label' => "CTA $i", 'href' => '/x', 'variant' => 'primary']],
    ];
}

$size   = (int) ($argv[1] ?? 50);   // 50 sections = 150 blocks by default
$blocks = [];
for ($i = 0; $i < $size; $i++) {
    $blocks = array_merge($blocks, makeBlock($i));
}
$context = ['name' => 'Alice', 'quote' => 'a memorable line'];

$n = count($blocks);
echo "Blocks: {$n}\n";

// Warm autoload + opcache.
LoggedCloud\PageStudio\Support\PageRenderer::render($blocks, $context);

function bench(callable $f, int $iters): float
{
    $start = microtime(true);
    for ($i = 0; $i < $iters; $i++) $f();
    return (microtime(true) - $start) / $iters * 1000;
}

$iters = 50;

config()->set('page-studio.render_cache.enabled', false);
$noCache = bench(fn () => LoggedCloud\PageStudio\Support\PageRenderer::render($blocks, $context), $iters);

config()->set('page-studio.render_cache.enabled', true);
// First run primes the cache; bench warm hits.
LoggedCloud\PageStudio\Support\PageRenderer::render($blocks, $context);
$warmCache = bench(fn () => LoggedCloud\PageStudio\Support\PageRenderer::render($blocks, $context), $iters);

// Cold cache: bust before every run by mutating the context.
$cold = 0;
for ($i = 0; $i < $iters; $i++) {
    $ctx = $context + ['nonce' => $i];
    $t0 = microtime(true);
    LoggedCloud\PageStudio\Support\PageRenderer::render($blocks, $ctx);
    $cold += microtime(true) - $t0;
}
$coldCache = $cold / $iters * 1000;

printf("Cache off · render: %6.2f ms (%.2f ms/block)\n", $noCache, $noCache / $n);
printf("Cache on  · warm:   %6.2f ms (%.2f ms/block)\n", $warmCache, $warmCache / $n);
printf("Cache on  · cold:   %6.2f ms (%.2f ms/block)\n", $coldCache, $coldCache / $n);
