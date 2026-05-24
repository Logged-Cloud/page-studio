<?php

namespace LoggedCloud\PageStudio\Blocks;

class BlockRegistry
{
    /** @var array<string, class-string<BlockType>> */
    protected static array $registered = [];

    public static function register(string $class): void
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Block class $class does not exist.");
        }
        if (! is_subclass_of($class, BlockType::class)) {
            throw new \InvalidArgumentException("$class must extend ".BlockType::class.'.');
        }
        $key = $class::key();
        if ($key === '') {
            throw new \InvalidArgumentException("$class::key() must return a non-empty identifier.");
        }
        self::$registered[$key] = $class;
    }

    /** @return array<string, class-string<BlockType>> */
    public static function all(): array
    {
        return self::$registered;
    }

    /** @return class-string<BlockType>|null */
    public static function find(string $key): ?string
    {
        return self::$registered[$key] ?? null;
    }

    public static function clear(): void
    {
        self::$registered = [];
    }
}
