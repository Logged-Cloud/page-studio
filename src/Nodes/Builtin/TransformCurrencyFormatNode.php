<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class TransformCurrencyFormatNode extends NodeType
{
    public static function key(): string   { return 'transform.currency_format'; }
    public static function label(): string { return 'Currency format'; }
    public static function icon(): string  { return '£'; }
    public static function group(): string { return 'transform'; }

    public static function inputs(): array  { return ['value' => ['label' => 'Amount', 'type' => 'any']]; }
    public static function outputs(): array { return ['value' => ['label' => 'Formatted', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'currency' => ['kind' => 'text',   'label' => 'Currency', 'default' => 'GBP'],
            'locale'   => ['kind' => 'text',   'label' => 'Locale',   'default' => 'en_GB'],
            'decimals' => ['kind' => 'number', 'label' => 'Decimals', 'default' => 2],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $val      = (float) ($inputs['value'] ?? 0);
        $currency = (string) ($settings['currency'] ?? 'GBP');
        $locale   = (string) ($settings['locale']   ?? 'en_GB');
        $decimals = (int) ($settings['decimals'] ?? 2);

        if (class_exists(\NumberFormatter::class)) {
            try {
                $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
                $fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
                $out = $fmt->formatCurrency($val, $currency);
                if ($out !== false) return ['value' => $out];
            } catch (\Throwable) {
                // fall through to fallback
            }
        }

        return ['value' => number_format($val, $decimals).' '.$currency];
    }
}
