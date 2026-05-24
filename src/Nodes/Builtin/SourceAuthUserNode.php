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

    public static function settings(): array
    {
        return [
            'expose_fields' => ['kind' => 'bool', 'label' => 'Expose fields as outputs', 'default' => false, 'help' => 'Show one socket per column instead of a single user output.'],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        $user   = auth()->check() ? auth()->user() : null;
        $expose = ! empty($settings['expose_fields']);

        if (! $expose) return ['user' => $user];
        if (! $user)   return [];

        $out = [];
        foreach ($user->attributesToArray() as $col => $v) {
            $out[$col] = $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v;
        }
        return $out;
    }
}
