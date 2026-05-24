<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Splits a raw URL string into ordered segments and detects already-bracketed
 * `{name}` variable holes. Used both server-side (saving) and client-side via
 * the same regex shape (Alpine evaluator).
 */
class PathParser
{
    /**
     * @return array<int, array{kind: 'literal'|'variable', value: string, position: int}>
     */
    public static function parse(string $path): array
    {
        $trimmed = trim($path, '/');
        if ($trimmed === '') return [];

        $parts = explode('/', $trimmed);
        $out   = [];

        foreach ($parts as $i => $raw) {
            $raw = trim($raw);
            if ($raw === '') continue;

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $raw, $m)) {
                $out[] = ['kind' => 'variable', 'value' => $m[1], 'position' => $i];
            } else {
                $out[] = ['kind' => 'literal', 'value' => $raw, 'position' => $i];
            }
        }

        return $out;
    }

    /**
     * Reverse of parse() · build a path string from an ordered segment array.
     * Variable segments are expected to be `{name}` already.
     */
    public static function compose(array $segments): string
    {
        $bits = [];
        foreach ($segments as $s) {
            $bits[] = $s['kind'] === 'variable' ? '{'.$s['value'].'}' : $s['value'];
        }
        return '/'.implode('/', $bits);
    }
}
