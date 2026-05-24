<?php

namespace LoggedCloud\PageStudio\Templates\Builtin;

use LoggedCloud\PageStudio\Templates\Template;

class UserProfileTemplate extends Template
{
    public static function name(): string  { return 'user-profile'; }
    public static function label(): string { return 'User profile'; }

    public static function description(): string
    {
        return 'A /users/{id} page that resolves the route id to an Eloquent User and renders the name + email.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'users.show',
            'method'        => 'GET',
            'path_template' => '/users/{id}',
            'description'   => 'User profile page · model-finder driven',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal',  'literal_value' => 'users'],
                ['position' => 1, 'kind' => 'variable', 'variable_name' => 'id'],
            ],
        ];
    }

    public static function variables(): array
    {
        return [[
            'name' => 'id', 'label' => 'User id', 'type' => 'int',
            'description' => 'Numeric primary key of the user',
            'examples' => ['1', '42', '1000'],
        ]];
    }

    public static function blocks(): array
    {
        return [
            self::block('heading',   ['text' => 'Welcome, {{ displayName }}!', 'level' => 'h1']),
            self::block('paragraph', ['text' => 'Email: {{ email }}']),
        ];
    }

    public static function graph(): array
    {
        return [
            'nodes' => [
                ['id' => 'src',  'type' => 'source.route_variable', 'position' => ['x' => 80,  'y' => 80],
                    'settings' => ['variable_name' => 'id']],
                ['id' => 'mf',   'type' => 'source.model_finder',   'position' => ['x' => 320, 'y' => 80],
                    'settings' => ['model_class' => 'App\\Models\\User', 'finder_key' => 'id', 'expose_fields' => true]],
                ['id' => 'out1', 'type' => 'output',                'position' => ['x' => 600, 'y' => 40],
                    'settings' => ['name' => 'displayName']],
                ['id' => 'out2', 'type' => 'output',                'position' => ['x' => 600, 'y' => 140],
                    'settings' => ['name' => 'email']],
            ],
            'edges' => [
                ['id' => 'e1', 'from_node' => 'src', 'from_socket' => 'value', 'to_node' => 'mf',   'to_socket' => 'key'],
                ['id' => 'e2', 'from_node' => 'mf',  'from_socket' => 'name',  'to_node' => 'out1', 'to_socket' => 'value'],
                ['id' => 'e3', 'from_node' => 'mf',  'from_socket' => 'email', 'to_node' => 'out2', 'to_socket' => 'value'],
            ],
        ];
    }
}
