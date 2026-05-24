<?php

namespace LoggedCloud\PageStudio\Nodes;

class NodeRegistry
{
    /** @var array<string, class-string<NodeType>> */
    protected static array $registered = [];

    /**
     * Register a developer-defined node class. Throws on bad shape so
     * misconfigurations fail loudly at boot instead of silently dropping
     * the node from the palette.
     */
    public static function register(string $class): void
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Node class $class does not exist.");
        }
        if (! is_subclass_of($class, NodeType::class)) {
            throw new \InvalidArgumentException("$class must extend ".NodeType::class.'.');
        }
        $key = $class::key();
        if ($key === '') {
            throw new \InvalidArgumentException("$class::key() must return a non-empty identifier.");
        }
        self::$registered[$key] = $class;
    }

    /** @return array<string, class-string<NodeType>> */
    public static function all(): array
    {
        return self::$registered;
    }

    /** @return class-string<NodeType>|null */
    public static function find(string $key): ?string
    {
        return self::$registered[$key] ?? null;
    }

    public static function clear(): void
    {
        self::$registered = [];
    }
}
