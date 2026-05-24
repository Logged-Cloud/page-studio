# Custom nodes with dynamic outputs

> A custom node whose **output sockets depend on its settings**. Same trick the built-in `source.model_finder` + `source.auth_user` use to expose one socket per column of a chosen model.

By default a `NodeType` declares its outputs statically via `static::outputs()`. That works for 90% of nodes: a transform with one input + one output, a constant, an HTTP-fetch with `body` + `status` + `headers`. The shape is known at design time.

The other 10% want to *introspect* something at design time (a model schema, the columns of a CSV, the fields returned by an external API) and expose **one output socket per discovered key**. That's what this tutorial covers.

## The hook

Override one instance method on your `NodeType`:

```php
public function dynamicOutputs(array $node): ?array
{
    // Return null to keep the static outputs() shape.
    // Return ['socket_key' => ['label' => '...', 'type' => '...']]
    // to replace it with a per-instance socket list.
}
```

The Blade canvas + the right-rail settings panel call this through `PageBuilder::outputsFor($node)`. The moment a setting flips, the canvas re-renders with the new socket list and wires can connect to the new sockets immediately.

Your `evaluate()` then returns the same keys you declared. The page-renderer's variable context picks them up exactly like any other node output.

## Worked example: a CSV-headers source

The goal: drop a "CSV columns" node on the canvas, paste a header line into a setting, and get one output socket per column. Downstream nodes can wire each column independently.

### 1 · The class

```php
namespace App\PageStudio\Nodes;

use LoggedCloud\PageStudio\Nodes\NodeType;

class CsvHeadersNode extends NodeType
{
    public static function key(): string   { return 'custom.csv_headers'; }
    public static function label(): string { return 'CSV columns'; }
    public static function icon(): string  { return '📋'; }
    public static function group(): string { return 'source'; }

    // Static fallback · used when no columns have been entered yet.
    public static function outputs(): array
    {
        return ['rows' => ['label' => 'Rows', 'type' => 'array']];
    }

    public static function settings(): array
    {
        return [
            'columns' => [
                'kind'    => 'text',
                'label'   => 'Comma-separated headers',
                'default' => '',
                'help'    => 'e.g. first_name, last_name, email',
            ],
        ];
    }

    public function dynamicOutputs(array $node): ?array
    {
        $cols = array_filter(array_map(
            'trim',
            explode(',', (string) ($node['settings']['columns'] ?? ''))
        ));
        if (empty($cols)) return null;          // fall back to static outputs()

        $outputs = [];
        foreach ($cols as $col) {
            $outputs[$col] = ['label' => $col, 'type' => 'string'];
        }
        return $outputs;
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        // At design time, just expose empty strings so the graph evaluates ·
        // a real implementation would load the file, pick the first row,
        // etc. The keys you return MUST match the dynamicOutputs() schema.
        $cols = array_filter(array_map('trim', explode(',', (string) ($settings['columns'] ?? ''))));
        $out  = [];
        foreach ($cols as $col) {
            $out[$col] = '';
        }
        return $out;
    }
}
```

### 2 · Register the node

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    \LoggedCloud\PageStudio\Nodes\NodeRegistry::register(\App\PageStudio\Nodes\CsvHeadersNode::class);
}
```

### 3 · Use it on the canvas

- Drop the node from the palette.
- Open its settings panel, paste `first_name, last_name, email` into the **Comma-separated headers** field.
- The node header now shows three output sockets, one per column. Wire any of them into downstream `output` nodes or transforms.

## How it falls back

`dynamicOutputs()` is a *hint*, not a contract. The hook returns the dynamic list when it can; the canvas falls back to the static schema in three cases:

1. The method returns `null` (your "I don't know yet, use the default" signal).
2. The method returns an empty array.
3. The method throws. Logged at debug level; the canvas stays usable.

Use this to your advantage: a model-introspecting node that can't reach the DB at design time should return `null` so the static `model` output stays available rather than disappearing.

## When the wires drop

Renaming or removing a socket via a settings change drops any wires that were attached to the old socket name. That's by design · a `first_name` socket renamed to `firstName` is a different connection point. If you want renames to follow, hold the socket key stable in `dynamicOutputs()` and rename only the human-facing `label`.

## Patterns from the built-in nodes

The two builtins that use this hook:

- **`source.model_finder`** · introspects an Eloquent model's table via `\LoggedCloud\PageStudio\Support\ModelFields::for($class)` and returns one socket per column, with the page-studio socket type mapped from the DB type (int / bool / string / array).
- **`source.auth_user`** · same as model_finder but the model class is pulled from `config('auth.providers.users.model')` so design-time introspection works even when no one is authenticated.

Both flip via an `expose_fields` bool setting. The canvas adds a header button (⚏) for any node whose type is in the built-in toggle list · if your custom node uses a similar pattern, expose a normal `bool` setting in its settings panel.

## Heads up · evaluation engine

The engine calls your `evaluate()` and uses whatever it returns; it doesn't re-check `dynamicOutputs()` per evaluation. Keep the two in sync: if `dynamicOutputs()` exposes `email`, `evaluate()` should return a key named `email` when `email` is in the settings.

Muted nodes (the "M" header button) take the first input and fan it across **all declared output sockets** via the static schema. A custom node with mostly-dynamic outputs that someone mutes may not behave intuitively · the workaround is to keep at least one stable static output that's meaningful when the node is bypassed.

---

## Related tutorials

- [Custom blocks](custom-blocks.md) · adding new block types to the page-builder palette
- [Custom nodes](custom-nodes.md) · the basic shape of a NodeType class without dynamic outputs
- [Custom templates](custom-templates.md) · packaging a starter route + page + node graph for `php artisan page-studio:install-template`
