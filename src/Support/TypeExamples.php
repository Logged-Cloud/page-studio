<?php

namespace LoggedCloud\PageStudio\Support;

/**
 * Stock example sets for each built-in variable type. Returned values are
 * guaranteed to match the type's `where` constraint, so the example-validator
 * in RouteBuilder accepts them without complaint.
 */
class TypeExamples
{
    /**
     * @return array<int, string>
     */
    public static function for(string $type): array
    {
        return match ($type) {
            'int' => ['1', '42', '1000'],

            // Slug must match `[a-z0-9](-?[a-z0-9])*` · no leading/trailing
            // dash, no double-dash, all lowercase.
            'slug' => ['hello-world', 'my-post', 'intro'],

            'uuid' => [
                '550e8400-e29b-41d4-a716-446655440000',
                '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
                'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            ],

            'alpha' => ['admin', 'guest', 'owner'],

            'any' => ['hello', 'foo-bar', 'anything-goes'],

            // enum + custom are user-defined · no canned defaults.
            'enum'   => [],
            'custom' => [],

            default => [],
        };
    }
}
