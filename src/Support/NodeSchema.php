<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Reads node-type config in a forgiving shape:
 *
 *   'inputs' => ['text' => 'Text']                            // legacy
 *   'inputs' => ['text' => ['label' => 'Text', 'type' => 'string']]
 *
 * normaliseSocket() expands either form into `['label', 'type']`. Keeping
 * the lookup in one place stops Blade / PHP / engine going out of sync.
 */
class NodeSchema
{
    /**
     * Stable set of socket types · the UI maps each to a colour, the engine
     * leaves coercion to PHP's loose typing.
     */
    public const TYPES = ['string', 'int', 'bool', 'array', 'object', 'model', 'collection', 'image', 'any'];

    public static function normaliseSocket(mixed $entry, string $defaultType = 'any'): array
    {
        if (is_string($entry)) {
            return ['label' => $entry, 'type' => $defaultType];
        }
        if (is_array($entry)) {
            return [
                'label' => (string) ($entry['label'] ?? ''),
                'type'  => (string) ($entry['type']  ?? $defaultType),
            ];
        }
        return ['label' => '', 'type' => $defaultType];
    }

    /**
     * Normalise every input + output entry on a node type so consumers can
     * always loop over the same shape.
     */
    public static function normalise(array $nodeType): array
    {
        $inputs = [];
        foreach ($nodeType['inputs'] ?? [] as $key => $entry) {
            $inputs[$key] = self::normaliseSocket($entry);
        }
        $outputs = [];
        foreach ($nodeType['outputs'] ?? [] as $key => $entry) {
            $outputs[$key] = self::normaliseSocket($entry);
        }
        return [
            'group'    => $nodeType['group']    ?? 'source',
            'label'    => $nodeType['label']    ?? '',
            'icon'     => $nodeType['icon']     ?? '',
            'inputs'   => $inputs,
            'outputs'  => $outputs,
            'settings' => $nodeType['settings'] ?? [],
        ];
    }
}
