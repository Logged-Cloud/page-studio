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
            'expose_fields' => ['kind' => 'bool', 'label' => 'Expose fields as outputs', 'default' => false, 'help' => 'Show one socket per column instead of a single model output.'],
        ];
    }

    public function dynamicOutputs(array $node): ?array
    {
        if (empty($node['settings']['expose_fields'])) return null;

        $fields = \LoggedCloud\PageStudio\Support\ModelFields::for(
            (string) ($node['settings']['model_class'] ?? ''),
        );
        if (empty($fields)) return null;

        $outputs = [];
        foreach ($fields as $col => $type) {
            $outputs[$col] = ['label' => $col, 'type' => $type];
        }
        return $outputs;
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

            $out = [];
            foreach ($model->attributesToArray() as $col => $v) {
                $out[$col] = $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v;
            }
            return $out;
        } catch (\Throwable) {
            return $expose ? [] : ['model' => null];
        }
    }
}
