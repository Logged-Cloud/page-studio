<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class SourceAuthIdNode extends NodeType
{
    public static function key(): string   { return 'source.auth_id'; }
    public static function label(): string { return 'Auth user id'; }
    public static function icon(): string  { return '#'; }
    public static function group(): string { return 'source'; }

    public static function outputs(): array { return ['value' => ['label' => 'Id', 'type' => 'int']]; }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => auth()->id()];
    }
}
