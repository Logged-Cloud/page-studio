<?php

namespace LoggedCloud\PageStudio\Support;

use LoggedCloud\PageStudio\Models\RouteDefinition;

/**
 * Turns a stored RouteDefinition into the data the host app needs to actually
 * register a Laravel route: method, URL pattern, where() constraints, and a
 * matrix of concrete example URLs (one per cartesian combination of variable
 * examples · capped to keep the count sane).
 */
class RouteCompiler
{
    public static function compile(RouteDefinition $route, int $exampleCap = 6): array
    {
        $segments = $route->segments()->with('variable')->get();

        $template = '/'.$segments->map(fn ($s) => $s->kind === 'variable'
            ? '{'.($s->variable->name ?? 'unknown').'}'
            : (string) $s->literal_value
        )->implode('/');

        $constraints = [];
        $exampleSets = [];
        foreach ($segments as $s) {
            if ($s->kind !== 'variable' || ! $s->variable) continue;
            $where = $s->variable->whereConstraint();
            if ($where) $constraints[$s->variable->name] = $where;
            $exampleSets[$s->variable->name] = $s->variable->examples ?: [];
        }

        return [
            'method'      => $route->method,
            'template'    => $template,
            'where'       => $constraints,
            'examples'    => self::expandExamples($template, $exampleSets, $exampleCap),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $sets
     * @return array<int, string>
     */
    protected static function expandExamples(string $template, array $sets, int $cap): array
    {
        if (! $sets) return [$template];

        $combos = [[]];
        foreach ($sets as $name => $vals) {
            if (! $vals) { $vals = ['{'.$name.'}']; }
            $next = [];
            foreach ($combos as $combo) {
                foreach ($vals as $v) {
                    $next[] = $combo + [$name => $v];
                    if (count($next) >= $cap) break;
                }
                if (count($next) >= $cap) break;
            }
            $combos = $next;
            if (count($combos) >= $cap) break;
        }

        return array_map(function ($combo) use ($template) {
            $url = $template;
            foreach ($combo as $name => $v) {
                $url = str_replace('{'.$name.'}', (string) $v, $url);
            }
            return $url;
        }, array_slice($combos, 0, $cap));
    }
}
