<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Builds a fresh block instance from a type key, seeding each setting with
 * the default declared in `config('page-studio.blocks.<type>.settings')`.
 * Layout blocks get an empty children array per declared slot.
 */
class BlockFactory
{
    public static function make(string $type): ?array
    {
        $schema = config("page-studio.blocks.$type");
        if (! $schema) return null;

        $settings = [];
        foreach ($schema['settings'] ?? [] as $key => $def) {
            $settings[$key] = $def['default'] ?? '';
        }

        $block = [
            'id'       => bin2hex(random_bytes(6)),
            'type'     => $type,
            'settings' => $settings,
        ];

        // Layout blocks get a children map keyed by slot name · the editor
        // relies on the slots existing as arrays from day one rather than
        // having to null-coalesce on every read.
        if (! empty($schema['slots'])) {
            $block['children'] = [];
            foreach ($schema['slots'] as $slot => $_label) {
                $block['children'][$slot] = [];
            }
        }

        return $block;
    }

    /**
     * Normalise a block tree · drops blocks of unknown types, fills missing
     * settings with defaults, and recurses into slots. Delegates to
     * BlockTree::sanitise() so the same rules apply at any depth.
     */
    public static function sanitiseAll(array $blocks): array
    {
        return BlockTree::sanitise($blocks);
    }
}
