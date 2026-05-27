<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Random number generator · float by default, integer when the
 * toggle is on. min / max / seed are settings-sockets so authors
 * can wire any upstream node (route variable, math result, etc.)
 * into them. A seed of null means "fresh random every evaluation";
 * a fixed seed makes the output deterministic which is handy for
 * test snapshots + previews.
 */
class SourceRandomNode extends NodeType
{
    public static function key(): string   { return 'source.random'; }
    public static function label(): string { return 'Random number'; }
    public static function icon(): string  { return '🎲'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'int']]; }

    public static function settings(): array
    {
        return [
            'min'     => ['kind' => 'number', 'label' => 'Min',     'default' => 0],
            'max'     => ['kind' => 'number', 'label' => 'Max',     'default' => 1],
            'integer' => ['kind' => 'bool',   'label' => 'Integer', 'default' => false, 'help' => 'Round to whole numbers.'],
            'seed'    => ['kind' => 'number', 'label' => 'Seed',    'default' => null,  'help' => 'Leave blank for fresh randomness, set for deterministic output.'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $min     = (float) ($settings['min'] ?? 0);
        $max     = (float) ($settings['max'] ?? 1);
        $integer = (bool)  ($settings['integer'] ?? false);
        $seedRaw =          $settings['seed'] ?? null;

        if ($min > $max) [$min, $max] = [$max, $min];

        // Deterministic when a seed is provided · drives mt_srand
        // for this single call, then re-seeds with the default RNG
        // so the rest of the request keeps real entropy.
        if ($seedRaw !== null && $seedRaw !== '') {
            mt_srand((int) $seedRaw);
        }
        try {
            if ($integer) {
                $lo = (int) ceil($min);
                $hi = (int) floor($max);
                if ($lo > $hi) $lo = $hi;
                $value = ($lo === $hi) ? $lo : mt_rand($lo, $hi);
            } else {
                $value = $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
            }
        } finally {
            if ($seedRaw !== null && $seedRaw !== '') {
                mt_srand();
            }
        }

        return ['value' => $value];
    }
}
