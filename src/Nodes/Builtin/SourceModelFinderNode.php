<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceModelFinderNode extends NodeType
{
    public static function key(): string   { return 'source.model_finder'; }
    public static function label(): string { return 'Model finder'; }
    public static function icon(): string  { return '🔍'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return ['key' => ['label' => 'Lookup key', 'type' => 'any']]; }
    public static function outputs(): array { return ['model' => ['label' => 'Model', 'type' => 'model']]; }

    public static function settings(): array
    {
        return [
            'model_class'   => ['kind' => 'text', 'label' => 'Model FQCN', 'default' => 'App\Models\User'],
            'finder_key'    => ['kind' => 'text', 'label' => 'Find by column', 'default' => 'id'],
            'expose_fields' => ['kind' => 'bool', 'label' => 'Expose fields as outputs', 'default' => true, 'help' => 'One socket per column · turn off to expose a single model output instead.'],
        ];
    }

    public function dynamicOutputs(array $node): ?array
    {
        if (empty($node['settings']['expose_fields'])) return null;
        $class = (string) ($node['settings']['model_class'] ?? '');

        $fields = \LoggedCloud\PageStudio\Support\ModelFields::for($class);
        if (empty($fields)) return null;

        $allowed = $this->allowedExposeCols($class, array_keys($fields));

        $outputs = [];
        foreach ($fields as $col => $type) {
            if (! in_array($col, $allowed, true)) continue;
            $outputs[$col] = ['label' => $col, 'type' => $type];
        }
        return $outputs ?: null;
    }

    /**
     * Filter the column list down to what the model has opted into
     * exposing as sockets. Two layers:
     *
     *  1. #[ExposeToModelFinder(expose: [...])] · explicit allowlist
     *     · the host author has chosen exactly which cols are safe.
     *  2. Fallback · respect Laravel's `$hidden` so sensitive cols
     *     (`password`, `remember_token`) stay out by default.
     *
     * @param array<int, string> $available
     * @return array<int, string>
     */
    protected function allowedExposeCols(string $class, array $available): array
    {
        $rec = \LoggedCloud\PageStudio\Support\ModelDiscovery::record($class);
        $explicit = $rec['expose'] ?? [];
        if (! empty($explicit)) {
            return array_values(array_intersect($available, $explicit));
        }

        // No explicit allowlist · drop the host model's $hidden cols.
        if ($class !== '' && class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
            try {
                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $class();
                $hidden   = $instance->getHidden();
                return array_values(array_diff($available, $hidden));
            } catch (\Throwable) {
                // Boot-time failures (no DB, unexpected ctor) · fall
                // through to "everything" rather than crash the canvas.
            }
        }
        return $available;
    }

    /**
     * Per-model finder_key dropdown · sourced from the selected
     * model's #[ExposeToModelFinder(findBy: [...])] declaration. If
     * no model is selected, or the selected model didn't list any
     * findBy columns, fall through to the static text-field default.
     */
    public function dynamicSettings(array $node): ?array
    {
        $class = trim((string) ($node['settings']['model_class'] ?? ''));
        if ($class === '') return null;

        $rec = \LoggedCloud\PageStudio\Support\ModelDiscovery::record($class);
        if (! $rec || empty($rec['findBy'])) return null;

        $options = [];
        foreach ($rec['findBy'] as $col) $options[$col] = $col;

        return [
            'finder_key' => [
                'kind'    => 'select',
                'label'   => 'Find by column',
                'default' => $rec['findBy'][0],
                'options' => $options,
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $class  = trim((string) ($settings['model_class'] ?? ''));
        $key    = trim((string) ($settings['finder_key']  ?? 'id'));
        $value  = $inputs['key'] ?? null;
        $expose = ! empty($settings['expose_fields']);

        if ($class === '' || $value === null || ! class_exists($class)) {
            return $expose ? [] : ['model' => null];
        }

        try {
            /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
            $model = $class::where($key ?: 'id', $value)->first();
            if (! $expose) return ['model' => $model];
            if (! $model) return [];

            // Same allowlist the socket list honours · keeps the
            // runtime output and the design-time outputs in sync, so
            // a hidden col can't leak even via a manually-wired edge.
            $available = array_keys($model->attributesToArray());
            $allowed   = $this->allowedExposeCols($class, $available);
            $out = [];
            foreach ($model->attributesToArray() as $col => $v) {
                if (! in_array($col, $allowed, true)) continue;
                $out[$col] = $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v;
            }
            return $out;
        } catch (\Throwable) {
            return $expose ? [] : ['model' => null];
        }
    }
}
