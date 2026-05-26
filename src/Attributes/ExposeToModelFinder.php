<?php

namespace LoggedCloud\PageStudio\Attributes;

/**
 * Mark an Eloquent model as available to the Model finder node in
 * the page-studio authoring UI. Opt-in · models without this
 * attribute are NEVER offered in the FQCN dropdown, so internal /
 * admin-only models don't leak into authoring surfaces.
 *
 * The composer hook `page-studio:discover-models` scans the host
 * app's `app/Models` tree for attributed classes and caches the
 * resulting map on disk · runtime boot reads the cache, no
 * per-request reflection.
 *
 * Example:
 *
 *     #[ExposeToModelFinder(
 *         label:      'Guest',
 *         findBy:     ['id', 'email', 'uuid'],
 *         searchable: ['name', 'email', 'phone'],
 *     )]
 *     class Customer extends Model {}
 *
 * - `label` · what the FQCN dropdown shows · defaults to the
 *   class basename when omitted.
 * - `findBy` · columns the finder node can lookup by · becomes
 *   the per-model `finder_key` dropdown. Defaults to ['id'].
 * - `searchable` · columns a future fuzzy-search surface can
 *   use · stored on the record now so the attribute is the
 *   single source of truth.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ExposeToModelFinder
{
    /**
     * @param array<int, string> $findBy
     * @param array<int, string> $searchable
     */
    public function __construct(
        public ?string $label = null,
        public array $findBy = ['id'],
        public array $searchable = [],
    ) {}
}
