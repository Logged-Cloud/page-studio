<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Address-by-path helper for the nested block tree.
 *
 * A path is a slash-separated string that alternates between numeric block
 * indices and slot names: e.g. `0/body/2/left/0` means
 *   blocks[0].children.body[2].children.left[0]
 *
 * The empty string `""` refers to the root array itself.
 *
 * Keeping the addressing logic in one place stops the Livewire component
 * growing a dozen near-duplicate get/set/insert helpers.
 */
class BlockTree
{
    /**
     * Walk down to the block at $path · returns the block array or null.
     */
    public static function get(array $blocks, string $path): ?array
    {
        if ($path === '') return null;
        $parts = self::parts($path);

        $cursor = $blocks;
        $block = null;
        foreach ($parts as $i => $p) {
            if ($i % 2 === 0) {
                // Numeric index into a block list.
                $block = $cursor[(int) $p] ?? null;
                if ($block === null) return null;
                $cursor = $block;
            } else {
                // Slot name into the current block's children.
                $cursor = $block['children'][$p] ?? [];
            }
        }
        return $block;
    }

    /**
     * Insert $newBlock at the given parentPath + slot + index. parentPath=''
     * is the root list (slot is then ignored).
     */
    public static function insert(array $blocks, string $parentPath, ?string $slot, int $index, array $newBlock): array
    {
        if ($parentPath === '') {
            array_splice($blocks, max(0, min($index, count($blocks))), 0, [$newBlock]);
            return $blocks;
        }

        return self::mutate($blocks, $parentPath, function (array &$parent) use ($slot, $index, $newBlock) {
            $parent['children'][$slot] ??= [];
            $kids = $parent['children'][$slot];
            array_splice($kids, max(0, min($index, count($kids))), 0, [$newBlock]);
            $parent['children'][$slot] = $kids;
        });
    }

    /**
     * Remove the block at $path. The path must point to a block, not a slot.
     */
    public static function remove(array $blocks, string $path): array
    {
        if ($path === '') return $blocks;

        [$parentPath, $slot, $index] = self::splitLast($path);

        if ($parentPath === '' && $slot === null) {
            unset($blocks[$index]);
            return array_values($blocks);
        }

        return self::mutate($blocks, $parentPath, function (array &$parent) use ($slot, $index) {
            $kids = $parent['children'][$slot] ?? [];
            unset($kids[$index]);
            $parent['children'][$slot] = array_values($kids);
        });
    }

    /**
     * Replace the block at $path with the supplied block.
     */
    public static function set(array $blocks, string $path, array $block): array
    {
        [$parentPath, $slot, $index] = self::splitLast($path);

        if ($parentPath === '' && $slot === null) {
            $blocks[$index] = $block;
            return $blocks;
        }

        return self::mutate($blocks, $parentPath, function (array &$parent) use ($slot, $index, $block) {
            $parent['children'][$slot][$index] = $block;
        });
    }

    /**
     * Move a block from one path to a (parentPath, slot, index) target.
     * Handles the index offset when moving inside the same parent + slot.
     */
    public static function move(array $blocks, string $fromPath, string $toParentPath, ?string $toSlot, int $toIndex): array
    {
        $block = self::get($blocks, $fromPath);
        if (! $block) return $blocks;

        [$fromParent, $fromSlot, $fromIndex] = self::splitLast($fromPath);

        // Same parent + slot · adjust the destination index for the gap left
        // behind by removing the source first.
        if ($fromParent === $toParentPath && $fromSlot === $toSlot && $toIndex > $fromIndex) {
            $toIndex--;
        }

        $blocks = self::remove($blocks, $fromPath);
        return self::insert($blocks, $toParentPath, $toSlot, $toIndex, $block);
    }

    /**
     * Sanitise the whole tree against the block-type config · drops unknown
     * types and prunes settings to the schema. Recurses into slots.
     */
    public static function sanitise(array $blocks): array
    {
        $clean = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;
            $schema = $type ? config("page-studio.blocks.$type") : null;
            if (! $schema) continue;

            $settings = [];
            foreach ($schema['settings'] ?? [] as $key => $def) {
                $settings[$key] = $block['settings'][$key] ?? ($def['default'] ?? '');
            }

            $children = [];
            foreach ($schema['slots'] ?? [] as $slotKey => $_label) {
                $kids = $block['children'][$slotKey] ?? [];
                $children[$slotKey] = is_array($kids) ? self::sanitise($kids) : [];
            }

            $entry = [
                'id'       => $block['id'] ?? bin2hex(random_bytes(6)),
                'type'     => $type,
                'settings' => $settings,
            ];
            if ($children) $entry['children'] = $children;
            $clean[] = $entry;
        }
        return $clean;
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected static function parts(string $path): array
    {
        return array_values(array_filter(explode('/', $path), fn ($b) => $b !== ''));
    }

    /**
     * Break a leaf path into (parentPath, lastSlot, lastIndex). For a
     * top-level path like "3", parentPath = '' and slot = null.
     */
    protected static function splitLast(string $path): array
    {
        $bits = array_values(array_filter(explode('/', $path), fn ($b) => $b !== ''));
        $lastIndex = (int) array_pop($bits);
        $lastSlot  = $bits ? array_pop($bits) : null;
        $parent    = implode('/', $bits);
        return [$parent, $lastSlot, $lastIndex];
    }

    /**
     * Walk to the parent at $parentPath and let the callback mutate it
     * by reference, then rebuild the tree along the way.
     */
    protected static function mutate(array $blocks, string $parentPath, callable $mutator): array
    {
        if ($parentPath === '') {
            // Caller asked for the root · the callback receives a fake
            // wrapper block whose `children['_root']` is the root list.
            $wrapper = ['children' => ['_root' => $blocks]];
            $mutator($wrapper);
            return $wrapper['children']['_root'];
        }

        $bits = array_values(array_filter(explode('/', $parentPath), fn ($b) => $b !== ''));
        // Bits alternate index, slot, index, slot, …, index (always odd count).
        return self::walk($blocks, $bits, $mutator);
    }

    protected static function walk(array $blocks, array $bits, callable $mutator): array
    {
        if (empty($bits)) return $blocks;

        $headIndex = (int) array_shift($bits);
        if (! isset($blocks[$headIndex])) return $blocks;

        if (empty($bits)) {
            // We've reached the target block · mutate it in place.
            $mutator($blocks[$headIndex]);
            return $blocks;
        }

        $slot = array_shift($bits);
        $children = $blocks[$headIndex]['children'][$slot] ?? [];
        $blocks[$headIndex]['children'][$slot] = self::walk($children, $bits, $mutator);
        return $blocks;
    }
}
