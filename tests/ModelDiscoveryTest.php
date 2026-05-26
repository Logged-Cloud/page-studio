<?php

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\PageStudio\Support\ModelDiscovery;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

function pageStudioFakeModelTree(string $base): array
{
    @mkdir($base, 0755, true);
    @mkdir("$base/Subnamespace", 0755, true);

    // Two attributed models · should land in the dropdown.
    file_put_contents("$base/Widget.php",
        "<?php\nnamespace Acme\\Models;\nuse LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n#[ExposeToModelFinder]\nclass Widget extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/Subnamespace/Nested.php",
        "<?php\nnamespace Acme\\Models\\Subnamespace;\nuse LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n#[ExposeToModelFinder]\nclass Nested extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    // One un-attributed Eloquent model · must NOT appear.
    file_put_contents("$base/Sprocket.php",
        "<?php\nnamespace Acme\\Models;\nclass Sprocket extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    // Not even an Eloquent model · must NOT appear.
    file_put_contents("$base/NotAModel.php", "<?php\nnamespace Acme\\Models;\nclass NotAModel { public string \$name = 'plain'; }\n");

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

it('scans a models directory and returns FQCN → nice-name for #[ExposeToModelFinder]-decorated classes only', function () {
    $base = sys_get_temp_dir().'/page-studio-models-'.uniqid();
    ['dir' => $dir, 'namespace' => $ns] = pageStudioFakeModelTree($base);

    $map = ModelDiscovery::scan($dir, $ns);

    expect($map)->toHaveKey('Acme\\Models\\Widget')
        ->and($map['Acme\\Models\\Widget'])->toBe('Widget')
        ->and($map)->toHaveKey('Acme\\Models\\Subnamespace\\Nested')
        ->and($map['Acme\\Models\\Subnamespace\\Nested'])->toBe('Nested')
        ->and($map)->not->toHaveKey('Acme\\Models\\Sprocket')
        ->and($map)->not->toHaveKey('Acme\\Models\\NotAModel');
});

it('records the attribute\'s findBy + searchable cols alongside the label · per-model config', function () {
    // Each decorated class declares its own lookup cols and search
    // cols. The discovery cache must preserve those so the finder
    // node UI can surface a per-model finder_key dropdown.
    $base = sys_get_temp_dir().'/page-studio-models-cfg-'.uniqid();
    @mkdir($base, 0755, true);

    file_put_contents("$base/Customer.php",
        "<?php\nnamespace CfgAcme\\Models;\n"
        ."use LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n"
        ."#[ExposeToModelFinder(label: 'Guest', findBy: ['id', 'email'], searchable: ['name', 'email', 'phone'])]\n"
        ."class Customer extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/Booking.php",
        "<?php\nnamespace CfgAcme\\Models;\n"
        ."use LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n"
        ."#[ExposeToModelFinder]\n"
        ."class Booking extends \\Illuminate\\Database\\Eloquent\\Model {}\n");

    require_once "$base/Customer.php";
    require_once "$base/Booking.php";

    $records = ModelDiscovery::records($base, 'CfgAcme\\Models');

    expect($records['CfgAcme\\Models\\Customer']['label'])->toBe('Guest')
        ->and($records['CfgAcme\\Models\\Customer']['findBy'])->toBe(['id', 'email'])
        ->and($records['CfgAcme\\Models\\Customer']['searchable'])->toBe(['name', 'email', 'phone'])
        // Defaults · undecorated args still produce a useable record.
        ->and($records['CfgAcme\\Models\\Booking']['label'])->toBe('Booking')
        ->and($records['CfgAcme\\Models\\Booking']['findBy'])->toBe(['id'])
        ->and($records['CfgAcme\\Models\\Booking']['searchable'])->toBe([]);
});

it('only includes models decorated with #[ExposeToModelFinder] · opt-in via the attribute', function () {
    // Mixed tree · some models attributed, some not. The scan must
    // ignore the un-attributed ones so the host app's internal models
    // don't leak into authoring UIs.
    $base = sys_get_temp_dir().'/page-studio-models-attr-'.uniqid();
    @mkdir($base, 0755, true);

    file_put_contents("$base/Booking.php",
        "<?php\nnamespace AttrAcme\\Models;\nuse LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n#[ExposeToModelFinder]\nclass Booking extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/SecretInternal.php",
        "<?php\nnamespace AttrAcme\\Models;\nclass SecretInternal extends \\Illuminate\\Database\\Eloquent\\Model {}\n");
    file_put_contents("$base/Customer.php",
        "<?php\nnamespace AttrAcme\\Models;\nuse LoggedCloud\\PageStudio\\Attributes\\ExposeToModelFinder;\n#[ExposeToModelFinder(label: 'Guest')]\nclass Customer extends \\Illuminate\\Database\\Eloquent\\Model {}\n");

    require_once "$base/Booking.php";
    require_once "$base/SecretInternal.php";
    require_once "$base/Customer.php";

    $map = ModelDiscovery::scan($base, 'AttrAcme\\Models');

    expect($map)->toHaveKey('AttrAcme\\Models\\Booking')
        ->and($map['AttrAcme\\Models\\Booking'])->toBe('Booking')
        ->and($map)->toHaveKey('AttrAcme\\Models\\Customer')
        ->and($map['AttrAcme\\Models\\Customer'])->toBe('Guest')
        ->and($map)->not->toHaveKey('AttrAcme\\Models\\SecretInternal');
});

it('returns an empty array when the models dir is missing', function () {
    $map = ModelDiscovery::scan('/does/not/exist', 'App\\Models');
    expect($map)->toBe([]);
});

it('writes and re-reads the cache file · accepts the legacy [fqcn => label] shape too', function () {
    // Legacy shape · still callable by old host-side tooling. The
    // writer normalises it into the new record structure so the
    // runtime always reads the same shape.
    $cache = sys_get_temp_dir().'/page-studio-models-cache-'.uniqid().'.php';
    ModelDiscovery::writeCache(['App\\Models\\User' => 'User'], $cache);

    expect(file_exists($cache))->toBeTrue();
    $loaded = require $cache;
    expect($loaded)->toBe([
        'App\\Models\\User' => ['label' => 'User', 'findBy' => ['id'], 'searchable' => []],
    ]);

    @unlink($cache);
});

it('the Model finder kind=select promotion survives the full provider boot order · regression for "still text input after release"', function () {
    // The bug · injectModelOptions ran BEFORE discoverNodeTypes, and
    // the latter rewrote every node's library entry from
    // toLibraryEntry() — wiping the kind=select promotion. The
    // user-facing symptom: dropdown never appears even with the
    // discovery cache populated.
    $cachePath = ModelDiscovery::cachePath();
    @mkdir(dirname($cachePath), 0755, true);
    ModelDiscovery::writeRecordCache([
        'App\\Models\\User' => ['label' => 'User', 'findBy' => ['id'], 'searchable' => []],
    ], $cachePath);

    // Re-run the FULL provider boot sequence in the same order the
    // provider does it · the previous order ran injectModelOptions
    // BEFORE discoverNodeTypes, and discoverNodeTypes rewrote every
    // node's library entry from toLibraryEntry(), wiping the
    // kind=select promotion. The fix moves injectModelOptions to
    // last so it has the final say.
    $provider = new \LoggedCloud\PageStudio\PageStudioServiceProvider(app());
    foreach (['registerBuiltinNodes', 'discoverNodeTypes', 'injectModelOptions'] as $m) {
        $ref = new ReflectionMethod($provider, $m);
        $ref->setAccessible(true);
        $ref->invoke($provider);
    }

    $library = config('page-studio.nodes', []);
    $entry   = $library['source.model_finder'] ?? [];

    expect($entry['settings']['model_class']['kind'] ?? null)->toBe('select',
        'After the full boot order, model_class must still be kind=select · the dropdown is what the user sees');
    expect($entry['settings']['model_class']['options'] ?? null)->toBe(
        ['App\\Models\\User' => 'User'],
        'options must carry the discovered map',
    );

    @unlink($cachePath);
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
