<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceAuthUserNode extends NodeType
{
    public static function key(): string   { return 'source.auth_user'; }
    public static function label(): string { return 'Auth user'; }
    public static function icon(): string  { return '👤'; }
    public static function group(): string { return 'source'; }

    public static function outputs(): array { return ['user' => ['label' => 'User', 'type' => 'model']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['user' => auth()->check() ? auth()->user() : null];
    }
}
