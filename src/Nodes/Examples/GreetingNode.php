<?php

namespace LoggedCloud\PageStudio\Nodes\Examples;

use LoggedCloud\PageStudio\Nodes\NodeType;

/**
 * Reference custom node · copy this into your host app's
 * `app/PageStudio/Nodes/` directory to use it as a template for your own
 * node types. NOT registered by default (the package only auto-discovers
 * host-app classes under `App\PageStudio\Nodes`).
 */
class GreetingNode extends NodeType
{
    public static function key(): string
    {
        return 'custom.greeting';
    }

    public static function label(): string
    {
        return 'Greeting';
    }

    public static function icon(): string
    {
        return '👋';
    }

    public static function group(): string
    {
        return 'transform';
    }

    public static function inputs(): array
    {
        return ['name' => ['label' => 'Name', 'type' => 'string']];
    }

    public static function outputs(): array
    {
        return ['value' => ['label' => 'Greeting', 'type' => 'string']];
    }

    public static function settings(): array
    {
        return [
            'greeting' => ['kind' => 'text', 'label' => 'Greeting', 'default' => 'Hello'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $name     = (string) ($inputs['name'] ?? 'friend');
        $greeting = (string) ($settings['greeting'] ?? 'Hello');
        return ['value' => "{$greeting}, {$name}!"];
    }
}
