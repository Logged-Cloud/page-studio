<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceHttpFetchNode extends NodeType
{
    public static function key(): string   { return 'source.http_fetch'; }
    public static function label(): string { return 'HTTP fetch'; }
    public static function icon(): string  { return '🌐'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array
    {
        return [
            'url'    => ['label' => 'URL',    'type' => 'string'],
            'bearer' => ['label' => 'Bearer', 'type' => 'string'],
        ];
    }
    public static function outputs(): array
    {
        return [
            'body'   => ['label' => 'Body',   'type' => 'string'],
            'json'   => ['label' => 'JSON',   'type' => 'any'],
            'status' => ['label' => 'Status', 'type' => 'int'],
        ];
    }

    public static function settings(): array
    {
        return [
            'method' => [
                'kind'    => 'select',
                'label'   => 'Method',
                'default' => 'GET',
                'options' => ['GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'],
            ],
            'ttl'             => ['kind' => 'number', 'label' => 'Cache TTL (seconds)', 'default' => 60],
            'header_accept'   => ['kind' => 'text',   'label' => 'Accept header',       'default' => 'application/json'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $url    = trim((string) ($inputs['url']    ?? ''));
        $bearer = trim((string) ($inputs['bearer'] ?? ''));
        $method = strtoupper((string) ($settings['method'] ?? 'GET'));
        $ttl    = (int) ($settings['ttl'] ?? 60);
        $accept = (string) ($settings['header_accept'] ?? 'application/json');

        if ($url === '') return ['body' => null, 'json' => null, 'status' => 0];

        $fetch = function () use ($url, $bearer, $method, $accept): array {
            if (! class_exists(\Illuminate\Support\Facades\Http::class)) {
                return ['body' => null, 'json' => null, 'status' => 0];
            }
            try {
                $pending = \Illuminate\Support\Facades\Http::withHeaders(array_filter([
                    'Accept'        => $accept ?: null,
                    'Authorization' => $bearer !== '' ? 'Bearer '.$bearer : null,
                ]));
                $response = $pending->send($method, $url);
                $body     = (string) $response->body();
                $decoded  = json_decode($body, true);
                return [
                    'body'   => $body,
                    'json'   => is_array($decoded) ? $decoded : null,
                    'status' => (int) $response->status(),
                ];
            } catch (\Throwable) {
                return ['body' => null, 'json' => null, 'status' => 0];
            }
        };

        if ($ttl <= 0 || ! class_exists(\Illuminate\Support\Facades\Cache::class)) {
            return $fetch();
        }

        $key = 'page-studio.http_fetch.'.sha1($method.'|'.$url.'|'.$bearer.'|'.$accept);
        try {
            return \Illuminate\Support\Facades\Cache::remember($key, $ttl, $fetch);
        } catch (\Throwable) {
            return $fetch();
        }
    }
}
