<?php

namespace LoggedCloud\PageStudio\Nodes\Builtin;

use LoggedCloud\PageStudio\Nodes\NodeType;
use LoggedCloud\PageStudio\Support\NodeHelpers;

class SourceRequestNode extends NodeType
{
    public static function key(): string   { return 'source.request'; }
    public static function label(): string { return 'Request data'; }
    public static function icon(): string  { return '⇥'; }
    public static function group(): string { return 'source'; }

    public static function inputs(): array  { return []; }
    public static function outputs(): array { return ['value' => ['label' => 'Value', 'type' => 'string']]; }

    public static function settings(): array
    {
        return [
            'property' => [
                'kind'    => 'select',
                'label'   => 'Field',
                'default' => 'path',
                'options' => [
                    'path'      => 'path',
                    'method'    => 'method',
                    'ip'        => 'ip',
                    'url'       => 'url',
                    'user_agent'=> 'user agent',
                    'host'      => 'host',
                ],
            ],
        ];
    }

    public function evaluate(array $inputs, array $settings, array $context): array
    {
        return ['value' => NodeHelpers::readRequestProperty((string) ($settings['property'] ?? 'path'))];
    }
}
