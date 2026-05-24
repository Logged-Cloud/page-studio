# Tutorial · build a custom node

The Blender-style node editor lets authors compose new variables out of route values, model lookups, and built-in transforms. **Custom nodes** extend that palette with PHP classes from your host app · no DB rows, no migrations, no Livewire wiring.

In this walkthrough we'll build a **GreetingNode** that takes a name input and a greeting setting and emits "Hi, Charles!" style output, then we'll wire it into a graph and assert the engine runs it end-to-end.

## What you'll end up with

A node in the Transform palette section that authors can drag onto the canvas, connect a `source.route_variable("userName")` to, and pipe its `value` output into an `output("greeting")` node so `{{ greeting }}` in the page renders as `"Hi, Charles!"`.

## 1 · Create the class

```bash
mkdir -p app/PageStudio/Nodes
```

```php
// app/PageStudio/Nodes/GreetingNode.php
namespace App\PageStudio\Nodes;

use LoggedCloud\PageStudio\Nodes\NodeType;

class GreetingNode extends NodeType
{
    public static function key(): string   { return 'custom.greeting'; }
    public static function label(): string { return 'Greeting'; }
    public static function icon(): string  { return '👋'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return ['name' => ['label' => 'Name', 'type' => 'string']];
    }

    public static function outputs(): array
    {
        return ['value' => ['label' => 'Greeting', 'type' => 'string']];
    }

    public static function settings(): array
    {
        return [
            'greeting' => ['kind' => 'text', 'label' => 'Greeting', 'default' => 'Hi'],
            'punctuation' => [
                'kind'    => 'select',
                'label'   => 'Punctuation',
                'default' => '!',
                'options' => ['!' => '!', '.' => '.', '?' => '?'],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $name = (string) ($inputs['name'] ?? 'friend');
        $greeting = (string) ($settings['greeting']    ?? 'Hi');
        $punct    = (string) ($settings['punctuation'] ?? '!');

        return ['value' => "{$greeting}, {$name}{$punct}"];
    }
}
```

Drop the file, reload the page-builder, open the node drawer · the **Greeting** chip appears in the Transform palette section. Drag it onto the canvas, connect a Route variable source to its `Name` input, wire its `Greeting` output into an Output node named `greeting`, and `{{ greeting }}` in your page now resolves to the formatted string.

## 2 · Anatomy

| Method | Required | What it controls |
|---|---|---|
| `key()` | yes | Identifier saved into the graph. Convention: `custom.<snake>`. |
| `label()` | yes | Human label shown in the palette + node headers. |
| `icon()` | no | Emoji / glyph for the node header. Defaults to `◆`. |
| `group()` | no | `'source'` / `'transform'` / `'image'` / `'output'` / `'note'` · drives the palette section. |
| `inputs()` | no | Socket map keyed by name. Empty for source nodes. |
| `outputs()` | no | Socket map keyed by name. Empty for output / note nodes. |
| `settings()` | no | Per-instance settings rendered into the right panel. Same kinds as blocks. |
| `evaluate($inputs, $settings, $context)` | yes | Returns the output socket values keyed by name. |

## 3 · Socket types

The `type` field on inputs + outputs colour-codes wires and warns on mismatched connections. Available types:

| Type | When to use it |
|---|---|
| `string` | Plain text. |
| `int` | Integers (and floats · the engine doesn't distinguish at the socket level). |
| `bool` | Toggles, conditions. |
| `array` | Lists. The `convert.to_array` node will normalise other shapes. |
| `object` / `model` | Eloquent models, DTOs, anything object-y. |
| `collection` | Laravel collections. |
| `image` | `['url' => string, 'filter' => string]` shape used by the image-pipeline nodes. |
| `any` | Disable type-checking on this socket. |

A wire from `string` → `string` renders solid + colour-coded. A wire from `int` → `string` renders dashed amber · the engine still passes the value through (coercion is forgiving) but the warning prompts the author to add a `convert.to_string` node.

## 4 · Source nodes (no inputs)

Build a "static value" or "API fetch" node by leaving `inputs()` empty:

```php
class CurrentSeasonNode extends NodeType
{
    public static function key(): string   { return 'custom.current_season'; }
    public static function label(): string { return 'Current season'; }
    public static function group(): string { return 'source'; }
    public static function outputs(): array
    {
        return ['value' => ['label' => 'Season', 'type' => 'string']];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $month = (int) date('n');
        return ['value' => match (true) {
            $month <= 2 || $month === 12 => 'winter',
            $month <= 5                  => 'spring',
            $month <= 8                  => 'summer',
            default                      => 'autumn',
        }];
    }
}
```

That's the entire node · authors drop it on the canvas and `{{ season }}` (via an Output node) resolves at render time.

## 5 · Multiple outputs

Return all of them from `evaluate()`:

```php
public static function outputs(): array
{
    return [
        'full'  => ['label' => 'Full name', 'type' => 'string'],
        'first' => ['label' => 'First',     'type' => 'string'],
        'last'  => ['label' => 'Last',      'type' => 'string'],
    ];
}

public function evaluate(array $inputs, array $settings, array $context): array
{
    $name = (string) ($inputs['name'] ?? '');
    [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
    return ['full' => $name, 'first' => $first, 'last' => $last];
}
```

Each output renders as its own socket on the right side of the node card.

## 6 · Calling an external API

Nothing special · `evaluate()` is plain PHP. Inject services via the container if you need them:

```php
use Illuminate\Support\Facades\Http;

class WeatherNode extends NodeType
{
    public static function key(): string   { return 'custom.weather'; }
    public static function label(): string { return 'Weather lookup'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array
    {
        return ['city' => ['label' => 'City', 'type' => 'string']];
    }

    public static function outputs(): array
    {
        return [
            'temp_c'    => ['label' => 'Temperature (°C)', 'type' => 'int'],
            'condition' => ['label' => 'Condition',         'type' => 'string'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $city = (string) ($inputs['city'] ?? '');
        if ($city === '') return ['temp_c' => null, 'condition' => null];

        $key = config('services.weather.key');
        $r = Http::get("https://api.weatherapi.com/v1/current.json", [
            'key' => $key, 'q' => $city,
        ])->json();

        return [
            'temp_c'    => $r['current']['temp_c']      ?? null,
            'condition' => $r['current']['condition']['text'] ?? null,
        ];
    }
}
```

The engine evaluates the graph once per render · cache aggressively (Laravel's `Cache::remember(...)`) so a marketing page hitting 1000 readers doesn't 1000x your API quota.

## 7 · Reading models from the context

The page-builder mount accepts whole Eloquent models in its `variables` prop. Inside a node you can read them off `$context`:

```php
public function evaluate(array $inputs, array $settings, array $context): array
{
    $user = $context['user'] ?? null;  // Eloquent User model
    if (! $user) return ['value' => null];

    return ['value' => $user->email_verified_at ? 'verified' : 'pending'];
}
```

Or pipe model attributes through dot-paths · the renderer flattens them automatically when the page is mounted, so `{{ user.email_verified_at }}` works without a custom node.

## 8 · Register from a sub-namespace

Auto-discovery walks `app/PageStudio/Nodes/` recursively. To pull from elsewhere:

```php
// config/page-studio.php
'node_paths' => [
    ['dir' => app_path('PageStudio/Nodes'),     'namespace' => 'App\\PageStudio\\Nodes'],
    ['dir' => app_path('Domain/Catalog/Nodes'), 'namespace' => 'App\\Domain\\Catalog\\Nodes'],
],
```

Or register explicitly:

```php
use LoggedCloud\PageStudio\Nodes\NodeRegistry;

NodeRegistry::register(\App\PageStudio\Nodes\WeatherNode::class);
```

## 9 · Verify

Add a Pest test that exercises the engine end-to-end:

```php
use App\PageStudio\Nodes\GreetingNode;
use LoggedCloud\PageStudio\Nodes\NodeRegistry;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;

it('greeting node formats name + greeting + punctuation', function () {
    NodeRegistry::register(GreetingNode::class);

    $nodes = [
        ['id' => 'n', 'type' => 'source.constant',  'settings' => ['value' => 'Alice']],
        ['id' => 'g', 'type' => 'custom.greeting',  'settings' => ['greeting' => 'Hi', 'punctuation' => '!']],
        ['id' => 'o', 'type' => 'output',           'settings' => ['name' => 'salutation']],
    ];
    $edges = [
        ['from_node' => 'n', 'from_socket' => 'value', 'to_node' => 'g', 'to_socket' => 'name'],
        ['from_node' => 'g', 'from_socket' => 'value', 'to_node' => 'o', 'to_socket' => 'value'],
    ];

    $ctx = NodeGraphEngine::evaluate($nodes, $edges, []);
    expect($ctx['salutation'])->toBe('Hi, Alice!');
});
```

That's the whole loop. The package ships a reference example at `src/Nodes/Examples/GreetingNode.php` you can crib from. Built-in nodes under `src/Nodes/Builtin/` are exactly this shape · `SourceModelFinderNode` for an Eloquent lookup, `TransformConcatNode` for two-input string ops, `ImageBrightnessNode` for image-pipeline filters.
