<?php

namespace LoggedCloud\PageStudio\Support;

use Illuminate\Database\Eloquent\Model;

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
     * Return the FQCN → nice-label map · prefers the on-disk cache, falls
     * back to scanning `app/Models` so first-run + dev environments still
     * see a populated dropdown.
     */
    public static function map(): array
    {
        $cache = self::cachePath();
        if (is_file($cache)) {
            $loaded = require $cache;
            if (is_array($loaded)) return $loaded;
        }
        return self::scan();
    }

    /**
     * Walk `app/Models` and return [FQCN => 'NiceName'] for every concrete
     * Eloquent model. Sub-namespaces are included.
     */
    public static function scan(?string $modelsDir = null, string $namespace = 'App\\Models'): array
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
            $found[$class] = class_basename($class);
        }
        ksort($found);
        return $found;
    }

    /**
     * Write the discovered map to disk in the same PHP-return-array format
     * Laravel uses for its bootstrap caches.
     */
    public static function writeCache(array $map, ?string $path = null): void
    {
        $path ??= self::cachePath();
        $dir = dirname($path);
        if (! is_dir($dir)) @mkdir($dir, 0755, true);
        $body = "<?php\n\nreturn ".var_export($map, true).";\n";
        file_put_contents($path, $body);
    }
}
