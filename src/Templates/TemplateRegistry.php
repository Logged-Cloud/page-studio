<?php

namespace LoggedCloud\PageStudio\Templates;

class TemplateRegistry
{
    /** @var array<string, class-string<Template>> */
    protected static array $registered = [];

    public static function register(string $class): void
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Template class $class does not exist.");
        }
        if (! is_subclass_of($class, Template::class)) {
            throw new \InvalidArgumentException("$class must extend ".Template::class.'.');
        }
        $name = $class::name();
        if ($name === '') {
            throw new \InvalidArgumentException("$class::name() must return a non-empty slug.");
        }
        self::$registered[$name] = $class;
    }

    /** @return array<string, class-string<Template>> */
    public static function all(): array
    {
        return self::$registered;
    }

    /** @return class-string<Template>|null */
    public static function find(string $name): ?string
    {
        return self::$registered[$name] ?? null;
    }

    public static function clear(): void
    {
        self::$registered = [];
    }
}
