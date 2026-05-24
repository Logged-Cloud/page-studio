<?php

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\PageStudio\Support\ModelDiscovery;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

function pageStudioFakeModelTree(string $base): array
{
    @mkdir($base, 0755, true);
    @mkdir("$base/Subnamespace", 0755, true);

    file_put_contents("$base/Widget.php", "<?php\nnamespace Acme\\Models;\nclass Widget extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/Sprocket.php", "<?php\nnamespace Acme\\Models;\nclass Sprocket extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/NotAModel.php", "<?php\nnamespace Acme\\Models;\nclass NotAModel { public string \$name = 'plain'; }\n");
    file_put_contents("$base/Subnamespace/Nested.php", "<?php\nnamespace Acme\\Models\\Subnamespace;\nclass Nested extends \\Illuminate\\Database\\Eloquent\\Model {}\n");

    // Eager-require so class_exists() returns true without an autoloader.
    require_once "$base/Widget.php";
    require_once "$base/Sprocket.php";
    require_once "$base/NotAModel.php";
    require_once "$base/Subnamespace/Nested.php";

    return [
        'dir'       => $base,
        'namespace' => 'Acme\\Models',
    ];
}

it('scans a models directory and returns FQCN → nice-name', function () {
    $base = sys_get_temp_dir().'/page-studio-models-'.uniqid();
    ['dir' => $dir, 'namespace' => $ns] = pageStudioFakeModelTree($base);

    $map = ModelDiscovery::scan($dir, $ns);

    expect($map)->toHaveKey('Acme\\Models\\Widget')
        ->and($map['Acme\\Models\\Widget'])->toBe('Widget')
        ->and($map)->toHaveKey('Acme\\Models\\Sprocket')
        ->and($map)->toHaveKey('Acme\\Models\\Subnamespace\\Nested')
        ->and($map['Acme\\Models\\Subnamespace\\Nested'])->toBe('Nested')
        ->and($map)->not->toHaveKey('Acme\\Models\\NotAModel');
});

it('returns an empty array when the models dir is missing', function () {
    $map = ModelDiscovery::scan('/does/not/exist', 'App\\Models');
    expect($map)->toBe([]);
});

it('writes and re-reads the cache file', function () {
    $cache = sys_get_temp_dir().'/page-studio-models-cache-'.uniqid().'.php';
    ModelDiscovery::writeCache(['App\\Models\\User' => 'User'], $cache);

    expect(file_exists($cache))->toBeTrue();
    $loaded = require $cache;
    expect($loaded)->toBe(['App\\Models\\User' => 'User']);

    @unlink($cache);
});

it('promotes the Model finder setting to a select when models are discovered', function () {
    // Pre-load a cache the service provider will read on boot.
    $cachePath = ModelDiscovery::cachePath();
    @mkdir(dirname($cachePath), 0755, true);
    ModelDiscovery::writeCache(['App\\Models\\Post' => 'Post', 'App\\Models\\User' => 'User'], $cachePath);

    // Re-run the boot hook (the test harness booted the provider with no
    // models present, so the schema is still the bare default).
    $provider = new \LoggedCloud\PageStudio\PageStudioServiceProvider(app());
    $ref = new ReflectionMethod($provider, 'injectModelOptions');
    $ref->setAccessible(true);
    $ref->invoke($provider);

    $schema = config('page-studio.nodes.source\.model_finder', null);
    // Dot-key trap · grab from the array directly.
    $library = config('page-studio.nodes', []);
    $entry   = $library['source.model_finder'];

    expect($entry['settings']['model_class']['kind'])->toBe('select')
        ->and($entry['settings']['model_class']['options'])->toBe([
            'App\\Models\\Post' => 'Post',
            'App\\Models\\User' => 'User',
        ]);

    @unlink($cachePath);
});
