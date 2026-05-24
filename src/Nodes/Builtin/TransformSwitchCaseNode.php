<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class TransformSwitchCaseNode extends NodeType
{
    public static function key(): string   { return 'transform.switch_case'; }
    public static function label(): string { return 'Switch / case'; }
    public static function icon(): string  { return '⊞'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array
    {
        return [
            'value'   => ['label' => 'Value',   'type' => 'any'],
            'default' => ['label' => 'Default', 'type' => 'any'],
        ];
    }
    public static function outputs(): array { return ['value' => ['label' => 'Result', 'type' => 'any']]; }

    public static function settings(): array
    {
        return [
            'cases' => [
                'kind'    => 'textarea',
                'label'   => 'Cases (expected|return per line)',
                'default' => "match|equal\nfallback|not equal",
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $needle = NodeHelpers::toString($inputs['value'] ?? null);
        $raw    = (string) ($settings['cases'] ?? '');

        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '|')) continue;
            [$expected, $return] = array_map('trim', explode('|', $line, 2));
            if ((string) $expected === $needle) return ['value' => $return];
        }

        return ['value' => $inputs['default'] ?? null];
    }
}
