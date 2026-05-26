<?php

namespace LoggedCloud\PageStudio\Support;

use Illuminate\Database\Eloquent\Model;
use LoggedCloud\PageStudio\Attributes\ExposeToModelFinder;

class ModelDiscovery
{
    /**
     * Path to the on-disk model-list cache · Composer's post-autoload-dump
     * hook in the host app refreshes it via `php artisan page-studio:discover-models`,
     * so runtime boot is free.
     */
    public static function cachePath(): string
    {
        return base_path('bootstrap/cache/page-studio-models.php');
    }

    /**
     * Return the FQCN → label map · the shape the node-settings
     * dropdown wants. Sourced from the richer record cache, derived
     * via attribute scan when no cache exists.
     *
     * @return array<class-string, string>
     */
    public static function map(): array
    {
        $records = self::records();
        $out = [];
        foreach ($records as $fqcn => $rec) {
            $out[$fqcn] = (string) ($rec['label'] ?? class_basename($fqcn));
        }
        return $out;
    }

    /**
     * Full record for a single attributed model · returns the {label,
     * findBy, searchable} tuple or null when the class isn't
     * #[ExposeToModelFinder]-decorated (or the cache is missing).
     *
     * @return array{label: string, findBy: array<int, string>, searchable: array<int, string>}|null
     */
    public static function record(string $fqcn): ?array
    {
        $records = self::records();
        return $records[$fqcn] ?? null;
    }

    /**
     * Return the full FQCN → {label, findBy, searchable} record map ·
     * prefers the on-disk cache, falls back to an on-the-fly attribute
     * scan so first-run + dev environments still see a populated
     * dropdown.
     *
     * @param string|null $modelsDir Override the directory to scan ·
     *        defaults to `app/Models`. Mostly used by tests.
     * @param string $namespace Override the namespace prefix · same
     *        story, mostly tests.
     * @return array<class-string, array{label: string, findBy: array<int, string>, searchable: array<int, string>}>
     */
    public static function records(?string $modelsDir = null, string $namespace = 'App\\Models'): array
    {
        // Tests pass an explicit directory · always rescan in that
        // case so the cache doesn't bleed across fixtures.
        if ($modelsDir !== null) {
            return self::scanRecords($modelsDir, $namespace);
        }

        $cache = self::cachePath();
        if (is_file($cache)) {
            $loaded = require $cache;
            if (is_array($loaded)) return $loaded;
        }
        return self::scanRecords();
    }

    /**
     * Back-compat alias · earlier callers (the node's dynamicOutputs,
     * the service provider, host-side tooling) use scan() to fetch
     * the bare label map. Keep the same shape so we don't ripple
     * changes through the host app's call sites.
     *
     * @return array<class-string, string>
     */
    public static function scan(?string $modelsDir = null, string $namespace = 'App\\Models'): array
    {
        $records = self::scanRecords($modelsDir, $namespace);
        $out = [];
        foreach ($records as $fqcn => $rec) {
            $out[$fqcn] = (string) ($rec['label'] ?? class_basename($fqcn));
        }
        return $out;
    }

    /**
     * Walk a models directory and return the per-class
     * #[ExposeToModelFinder] record for every concrete Eloquent
     * model that carries the attribute. Un-attributed models are
     * silently skipped · opt-in is the whole point of this surface.
     *
     * @return array<class-string, array{label: string, findBy: array<int, string>, searchable: array<int, string>}>
     */
    public static function scanRecords(?string $modelsDir = null, string $namespace = 'App\\Models'): array
    {
        $modelsDir ??= function_exists('app_path') ? app_path('Models') : '';
        if (! $modelsDir || ! is_dir($modelsDir)) return [];

        $found = [];
        $rii   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modelsDir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($rii as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;
            $rel = trim(str_replace([$modelsDir, '.php'], '', $file->getPathname()), DIRECTORY_SEPARATOR);
            if ($rel === '') continue;
            $class = $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
            if (! class_exists($class)) continue;
            try {
                $ref = new \ReflectionClass($class);
            } catch (\Throwable) {
                continue;
            }
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) continue;

            $attrs = $ref->getAttributes(ExposeToModelFinder::class);
            if (empty($attrs)) continue;

            /** @var ExposeToModelFinder $attr */
            $attr = $attrs[0]->newInstance();
            $found[$class] = [
                'label'      => $attr->label ?? class_basename($class),
                'findBy'     => array_values($attr->findBy),
                'searchable' => array_values($attr->searchable),
            ];
        }
        ksort($found);
        return $found;
    }

    /**
     * Write the discovered LABEL map to disk in the same shape the
     * earlier cache used · kept for back-compat with host-side
     * tooling that may still call this. New writes should prefer
     * writeRecordCache() so the full per-model config makes it onto
     * disk.
     */
    public static function writeCache(array $map, ?string $path = null): void
    {
        $records = [];
        foreach ($map as $fqcn => $label) {
            $records[$fqcn] = ['label' => (string) $label, 'findBy' => ['id'], 'searchable' => []];
        }
        self::writeRecordCache($records, $path);
    }

    /**
     * Persist the rich {label, findBy, searchable} record map · this
     * is the shape the dropdown and the dynamic finder_key surface
     * consume at runtime.
     *
     * @param array<class-string, array{label: string, findBy: array<int, string>, searchable: array<int, string>}> $records
     */
    public static function writeRecordCache(array $records, ?string $path = null): void
    {
        $path ??= self::cachePath();
        $dir = dirname($path);
        if (! is_dir($dir)) @mkdir($dir, 0755, true);
        $body = "<?php\n\nreturn ".var_export($records, true).";\n";
        file_put_contents($path, $body);
    }
}
