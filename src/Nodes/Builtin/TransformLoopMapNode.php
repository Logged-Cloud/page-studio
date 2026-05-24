<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformLoopMapNode extends NodeType
{
    public static function key(): string   { return 'transform.loop_map'; }
    public static function label(): string { return 'Loop map'; }
    public static function icon(): string  { return '↻'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'array'    => ['label' => 'Array',    'type' => 'array'],
            'template' => ['label' => 'Template', 'type' => 'string'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Mapped', 'type' => 'array']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $items    = NodeHelpers::toArray($inputs['array'] ?? null);
        $template = (string) ($inputs['template'] ?? '');
        if ($items === [] || $template === '') return ['value' => []];

        $mapped = [];
        $i = 0;
        foreach ($items as $item) {
            $mapped[] = preg_replace_callback(
                '/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/',
                function ($m) use ($item, $i) {
                    $token = $m[1];
                    if ($token === 'index') return (string) $i;
                    if ($token === 'item')  return NodeHelpers::toString($item);
                    $path = str_starts_with($token, 'item.') ? substr($token, 5) : $token;
                    return NodeHelpers::toString(data_get($item, $path));
                },
                $template,
            );
            $i++;
        }
        return ['value' => $mapped];
    }
}
