<?php

namespace LoggedCloud\PageStudio\Support;

use Illuminate\Database\Eloquent\Model;

class ModelFields
{
    /**
     * Per-request cache so opening a node settings panel + rendering the
     * canvas doesn't re-query the schema for every paint.
     *
     * @var array<class-string, array<string, string>>
     */
    protected static array $cache = [];

    /**
     * Return `[column => page-studio-socket-type]` for the given Eloquent
     * model class. Reads the live schema via the model's connection so
     * sub-namespace + per-app connections are honoured. Failures (no DB,
     * unknown class, missing table) return an empty array so the caller
     * can fall back to the static schema's single `model` output.
     */
    public static function for(string $class): array
    {
        if ($class === '') return [];
        if (isset(self::$cache[$class])) return self::$cache[$class];

        try {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                return self::$cache[$class] = [];
            }
            /** @var Model $instance */
            $instance = new $class;
            $schema   = $instance->getConnection()->getSchemaBuilder();
            $table    = $instance->getTable();
            if (! $schema->hasTable($table)) return self::$cache[$class] = [];

            $columns = $schema->getColumnListing($table);
            $fields  = [];
            foreach ($columns as $col) {
                try {
                    $dbType = $schema->getColumnType($table, $col);
                } catch (\Throwable) {
                    $dbType = 'string';
                }
                $fields[$col] = self::toSocketType($dbType);
            }
            return self::$cache[$class] = $fields;
        } catch (\Throwable) {
            return self::$cache[$class] = [];
        }
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * Seed the per-request cache for a class · used by tests that
     * exercise dynamicOutputs / evaluate without spinning up a real
     * DB schema. Production code should never call this.
     *
     * @param array<string, string> $fields
     */
    public static function seed(string $class, array $fields): void
    {
        self::$cache[$class] = $fields;
    }

    /**
     * Map a DBAL / Laravel column type onto one of the page-studio socket
     * type names. Unknown types fall through to `string` so the wire still
     * connects without a type mismatch warning.
     */
    protected static function toSocketType(string $dbType): string
    {
        $dbType = strtolower($dbType);
        return match (true) {
            in_array($dbType, ['integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int'], true) => 'int',
            in_array($dbType, ['decimal', 'double', 'float', 'real', 'numeric'], true)               => 'int',
            $dbType === 'boolean' || $dbType === 'bool'                                              => 'bool',
            in_array($dbType, ['json', 'jsonb', 'array'], true)                                       => 'array',
            default                                                                                   => 'string',
        };
    }
}
