<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Static utility helpers used by built-in NodeType implementations. Kept
 * separate from NodeGraphEngine so individual node classes can compose
 * them without reaching into the engine's internals.
 */
class NodeHelpers
{
    public static function math(float $a, float $b, string $op): float|int|null
    {
        $r = match ($op) {
            '-'     => $a - $b,
            '*'     => $a * $b,
            '/'     => $b == 0 ? null : $a / $b,
            '%'     => $b == 0 ? null : fmod($a, $b),
            default => $a + $b,
        };
        if ($r === null) return null;
        return floor($r) == $r ? (int) $r : $r;
    }

    public static function joinArrayLike(mixed $value, string $separator): string
    {
        if (is_array($value)) {
            return implode($separator, array_map(fn ($v) => self::toString($v), $value));
        }
        if ($value instanceof \IteratorAggregate || $value instanceof \Traversable) {
            $bits = [];
            foreach ($value as $item) $bits[] = self::toString($item);
            return implode($separator, $bits);
        }
        return is_scalar($value) ? (string) $value : '';
    }

    public static function firstOf(mixed $value): mixed
    {
        if (is_array($value)) return $value[array_key_first($value)] ?? null;
        if ($value instanceof \Illuminate\Support\Collection) return $value->first();
        if ($value instanceof \Traversable) {
            foreach ($value as $item) return $item;
        }
        return null;
    }

    public static function toString(mixed $value): string
    {
        if ($value === null) return '';
        if (is_string($value)) return $value;
        if (is_scalar($value)) return (string) $value;
        if ($value instanceof \DateTimeInterface) return $value->format(DATE_ATOM);
        if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
        return (string) json_encode($value);
    }

    public static function toBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return ! in_array($v, ['', '0', 'false', 'no', 'off', 'null'], true);
        }
        if (is_numeric($value)) return (float) $value !== 0.0;
        if (is_array($value)) return ! empty($value);
        return $value !== null;
    }

    public static function toArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        if ($value === null || $value === '') return [];
        if ($value instanceof \Illuminate\Support\Collection) return $value->all();
        if ($value instanceof \Traversable) return iterator_to_array($value);
        if (is_object($value) && method_exists($value, 'toArray')) return (array) $value->toArray();
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) return $decoded;
            return [$value];
        }
        return [$value];
    }

    public static function formatDate(mixed $value, string $format, int $offsetAmount = 0, string $offsetUnit = 'days'): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            $dt = $value instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($value)
                : new \DateTimeImmutable((string) $value);
            if ($offsetAmount !== 0) {
                $sign = $offsetAmount > 0 ? '+' : '-';
                $unit = in_array($offsetUnit, ['minutes', 'hours', 'days', 'weeks', 'months', 'years'], true)
                    ? $offsetUnit
                    : 'days';
                $dt = $dt->modify($sign.abs($offsetAmount).' '.$unit);
            }
            $fmt = $format ?: 'Y-m-d';
            return match (strtolower($fmt)) {
                'iso', 'iso8601'    => $dt->format(\DATE_ATOM),
                'timestamp', 'unix' => (string) $dt->format('U'),
                default             => $dt->format($fmt),
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public static function readField(mixed $object, string $field): mixed
    {
        if ($object === null || $field === '') return null;
        $value = data_get($object, $field);
        if ($value instanceof \DateTimeInterface) return $value->format(DATE_ATOM);
        if (is_object($value) && method_exists($value, '__toString')) return (string) $value;
        if (is_scalar($value) || $value === null) return $value;
        return json_encode($value);
    }

    public static function readRequestProperty(string $property): ?string
    {
        if (! function_exists('request') || ! request()) return null;
        $req = request();
        return match ($property) {
            'method'    => (string) $req->method(),
            'ip'        => (string) ($req->ip() ?? ''),
            'url'       => (string) $req->fullUrl(),
            'user_agent'=> (string) ($req->userAgent() ?? ''),
            'host'      => (string) ($req->getHost() ?? ''),
            default     => (string) $req->path(),
        };
    }

    public static function imageFilter(mixed $image, string $newFilter): ?array
    {
        if (! is_array($image) || ! isset($image['url'])) return null;
        $current = trim((string) ($image['filter'] ?? ''));
        return [
            'url'    => (string) $image['url'],
            'filter' => $current === '' ? $newFilter : $current.' '.$newFilter,
        ];
    }
}
