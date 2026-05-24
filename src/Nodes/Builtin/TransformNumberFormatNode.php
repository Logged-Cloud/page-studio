<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformNumberFormatNode extends NodeType
{
    public static function key(): string   { return 'transform.number_format'; }
    public static function label(): string { return 'Number format'; }
    public static function icon(): string  { return '#,#'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Number', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Formatted', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'decimals'            => ['kind' => 'number', 'label' => 'Decimals',            'default' => 0],
            'thousands_separator' => ['kind' => 'text',   'label' => 'Thousands separator', 'default' => ','],
            'decimal_separator'   => ['kind' => 'text',   'label' => 'Decimal separator',   'default' => '.'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $val         = (float) ($inputs['value'] ?? 0);
        $decimals    = (int) ($settings['decimals'] ?? 0);
        $thousands   = (string) ($settings['thousands_separator'] ?? ',');
        $decimalSep  = (string) ($settings['decimal_separator']   ?? '.');
        return ['value' => number_format($val, $decimals, $decimalSep, $thousands)];
    }
}
