<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;

class NoteNode extends NodeType
{
    public static function key(): string   { return 'note'; }
    public static function label(): string { return 'Sticky note'; }
    public static function icon(): string  { return '✎'; }
    public static function group(): string { return 'note'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return []; }

    public static function settings(): array
    {
        return [
            'text' => [
                'kind'    => 'textarea',
                'label'   => 'Note',
                'default' => 'A reminder about what this slice of the graph does.',
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return [];
    }
}
