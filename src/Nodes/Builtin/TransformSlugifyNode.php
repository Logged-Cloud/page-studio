<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use Illuminate\Support\Str;
use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformSlugifyNode extends NodeType
{
    public static function key(): string   { return 'transform.slugify'; }
    public static function label(): string { return 'Slugify'; }
    public static function icon(): string  { return '-'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['text' => ['label' => 'Text', 'type' => 'string']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Slug', 'type' => 'string']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => is_scalar($inputs['text'] ?? null) ? Str::slug((string) $inputs['text']) : null];
    }
}
