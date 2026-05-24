<?php

return [

    'table_prefix' => 'page_studio_',
    'connection'   => null,
    'route_prefix' => 'studio/preview',

    /*
     * Render-result cache · PageRenderer::render() (and the email/text/markdown
     * counterparts) wraps the top-level render in Cache::remember, keyed by a
     * sha1 of the block tree + context + render mode. Disabled by default so
     * existing apps don't change behaviour. Turn on for the public-facing page
     * render path where blocks change rarely. Editor mode (`$decorate=true`)
     * always bypasses the cache regardless.
     */
    'render_cache' => [
        'enabled' => env('PAGE_STUDIO_RENDER_CACHE', false),
        'store'   => env('PAGE_STUDIO_RENDER_CACHE_STORE'),
        'ttl'     => (int) env('PAGE_STUDIO_RENDER_CACHE_TTL', 3600),
    ],

    'variable_types' => [
        'int' => [
            'label'    => 'Integer',
            'where'    => '[0-9]+',
            'validate' => 'integer',
            'example'  => '42',
        ],
        'slug' => [
            'label'    => 'Slug',
            'where'    => '[a-z0-9](-?[a-z0-9])*',
            'validate' => 'regex:/^[a-z0-9](-?[a-z0-9])*$/',
            'example'  => 'hello-world',
        ],
        'uuid' => [
            'label'    => 'UUID',
            'where'    => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
            'validate' => 'uuid',
            'example'  => '550e8400-e29b-41d4-a716-446655440000',
        ],
        'alpha' => [
            'label'    => 'Letters only',
            'where'    => '[A-Za-z]+',
            'validate' => 'alpha',
            'example'  => 'admin',
        ],
        'enum' => [
            'label'    => 'One of N values',
            'where'    => null,
            'validate' => null,
            'example'  => 'draft',
        ],
        'any' => [
            'label'    => 'Anything',
            'where'    => '[^/]+',
            'validate' => 'string',
            'example'  => 'free-form',
        ],
        'custom' => [
            'label'    => 'Custom regex',
            'where'    => null,
            'validate' => null,
            'example'  => '',
        ],
    ],

    /*
    | Host-app gate name (e.g. 'page-studio.manage'). When set, every
    | Livewire component checks `Gate::allows()` on mount and throws
    | a 403 when the policy refuses. Leave null to disable.
    */
    'gate' => null,

    /*
    | Switch off the bottom-drawer node editor entirely · the "Show nodes"
    | button vanishes and the drawer never renders. Existing saved graphs
    | are still evaluated, so output-node variables keep flowing into
    | pages · the host app just won't expose the editor surface.
    */
    'enable_node_editor' => true,

    /*
    | Node types to hide from the palette / context menu / quick-add picker.
    | Identifiers come from `nodes` below (e.g. ['source.auth_user',
    | 'image.upload']). Existing nodes of these types in a saved graph
    | still evaluate · the gate is UI-only.
    */
    'disabled_nodes' => [],

    /*
    | Block types to hide from the page-builder palette. Same UI-only gate
    | as disabled_nodes · existing blocks keep rendering.
    */
    'disabled_blocks' => [],

    'min_examples_per_variable' => 3,

    /*
    | Node library · the Blender-style variable-composition graph.
    | Each entry declares:
    |   - group:   'source' | 'transform' | 'output' (palette section)
    |   - label, icon
    |   - inputs:  socket key → human label · empty for sources
    |   - outputs: socket key → human label · empty for the Output node
    |   - settings: schema like the block library (kind / label / default / options)
    |   - evaluator: ['class' => Fqcn, 'method' => '...'] OR a callable in the
    |     evaluators array below · the engine looks up by node type.
    */
    'nodes' => [
        // ─── Sources ───────────────────────────────────────────────────────
        'source.route_variable' => [
            'group'    => 'source',
            'label'    => 'Route variable',
            'icon'     => '◇',
            'inputs'   => [],
            'outputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'settings' => [
                'variable_name' => ['kind' => 'text', 'label' => 'Variable name', 'default' => ''],
            ],
        ],
        'source.constant' => [
            'group'    => 'source',
            'label'    => 'Constant',
            'icon'     => '▣',
            'inputs'   => [],
            'outputs'  => ['value' => ['label' => 'Value', 'type' => 'string']],
            'settings' => [
                'value' => ['kind' => 'text', 'label' => 'Value', 'default' => ''],
            ],
        ],
        'source.auth_user' => [
            'group'    => 'source',
            'label'    => 'Auth user',
            'icon'     => '👤',
            'inputs'   => [],
            'outputs'  => ['user' => ['label' => 'User', 'type' => 'model']],
            'settings' => [],
        ],
        'source.auth_id' => [
            'group'    => 'source',
            'label'    => 'Auth user id',
            'icon'     => '#',
            'inputs'   => [],
            'outputs'  => ['value' => ['label' => 'Id', 'type' => 'int']],
            'settings' => [],
        ],
        'source.request' => [
            'group'    => 'source',
            'label'    => 'Request data',
            'icon'     => '⇥',
            'inputs'   => [],
            'outputs'  => ['value' => ['label' => 'Value', 'type' => 'string']],
            'settings' => [
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
            ],
        ],
        'source.now' => [
            'group'    => 'source',
            'label'    => 'Now (datetime)',
            'icon'     => '🕒',
            'inputs'   => [],
            'outputs'  => ['value' => ['label' => 'Datetime', 'type' => 'object']],
            'settings' => [],
        ],
        'source.model_finder' => [
            'group'    => 'source',
            'label'    => 'Model finder',
            'icon'     => '🔍',
            'inputs'   => ['key' => ['label' => 'Lookup key', 'type' => 'any']],
            'outputs'  => ['model' => ['label' => 'Model', 'type' => 'model']],
            'settings' => [
                'model_class'   => ['kind' => 'text', 'label' => 'Model FQCN', 'default' => 'App\Models\User'],
                'finder_key'    => ['kind' => 'text', 'label' => 'Find by column', 'default' => 'id'],
                'expose_fields' => ['kind' => 'bool', 'label' => 'Expose fields as outputs', 'default' => false, 'help' => 'Show one socket per column instead of a single model output.'],
            ],
        ],

        // ─── Text transforms ───────────────────────────────────────────────
        'transform.uppercase' => [
            'group' => 'transform', 'label' => 'Uppercase', 'icon' => 'A',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Uppercased', 'type' => 'string']],
            'settings' => [],
        ],
        'transform.lowercase' => [
            'group' => 'transform', 'label' => 'Lowercase', 'icon' => 'a',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Lowercased', 'type' => 'string']],
            'settings' => [],
        ],
        'transform.trim' => [
            'group' => 'transform', 'label' => 'Trim whitespace', 'icon' => '⌐',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Trimmed', 'type' => 'string']],
            'settings' => [],
        ],
        'transform.concat' => [
            'group' => 'transform', 'label' => 'Concatenate', 'icon' => '+',
            'inputs'  => [
                'a' => ['label' => 'A', 'type' => 'string'],
                'b' => ['label' => 'B', 'type' => 'string'],
            ],
            'outputs' => ['value' => ['label' => 'A + B', 'type' => 'string']],
            'settings' => [
                'separator' => ['kind' => 'text', 'label' => 'Separator', 'default' => ''],
            ],
        ],
        'transform.replace' => [
            'group' => 'transform', 'label' => 'Replace text', 'icon' => '⇄',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Result', 'type' => 'string']],
            'settings' => [
                'find'    => ['kind' => 'text', 'label' => 'Find', 'default' => ''],
                'replace' => ['kind' => 'text', 'label' => 'Replace with', 'default' => ''],
            ],
        ],
        'transform.slugify' => [
            'group' => 'transform', 'label' => 'Slugify', 'icon' => '-',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Slug', 'type' => 'string']],
            'settings' => [],
        ],
        'transform.length' => [
            'group' => 'transform', 'label' => 'Length', 'icon' => '#',
            'inputs'  => ['value' => ['label' => 'String or array', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Length', 'type' => 'int']],
            'settings' => [],
        ],
        'transform.split' => [
            'group' => 'transform', 'label' => 'Split to array', 'icon' => '⫝',
            'inputs'  => ['text' => ['label' => 'Text', 'type' => 'string']],
            'outputs' => ['value' => ['label' => 'Parts', 'type' => 'array']],
            'settings' => [
                'delimiter' => ['kind' => 'text', 'label' => 'Delimiter', 'default' => ','],
            ],
        ],
        'transform.join' => [
            'group' => 'transform', 'label' => 'Join array', 'icon' => '⋃',
            'inputs'  => ['array' => ['label' => 'Array', 'type' => 'array']],
            'outputs' => ['value' => ['label' => 'Joined', 'type' => 'string']],
            'settings' => [
                'separator' => ['kind' => 'text', 'label' => 'Separator', 'default' => ', '],
            ],
        ],
        'transform.format_date' => [
            'group' => 'transform', 'label' => 'Format date', 'icon' => '🗓',
            'inputs'  => ['value' => ['label' => 'Date / datetime', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Formatted', 'type' => 'string']],
            'settings' => [
                'format' => ['kind' => 'text', 'label' => 'Format', 'default' => 'Y-m-d'],
            ],
        ],

        // ─── Value transforms ──────────────────────────────────────────────
        'transform.default' => [
            'group' => 'transform', 'label' => 'Default when empty', 'icon' => '∅',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Value or fallback', 'type' => 'any']],
            'settings' => [
                'fallback' => ['kind' => 'text', 'label' => 'Fallback', 'default' => ''],
            ],
        ],
        'transform.field' => [
            'group' => 'transform', 'label' => 'Read field', 'icon' => '.',
            'inputs'  => ['object' => ['label' => 'Object / model', 'type' => 'object']],
            'outputs' => ['value' => ['label' => 'Field value', 'type' => 'any']],
            'settings' => [
                'field' => ['kind' => 'text', 'label' => 'Field name', 'default' => 'name'],
            ],
        ],
        'transform.equals' => [
            'group' => 'transform', 'label' => 'Equals?', 'icon' => '=',
            'inputs'  => [
                'a' => ['label' => 'A', 'type' => 'any'],
                'b' => ['label' => 'B', 'type' => 'any'],
            ],
            'outputs' => ['value' => ['label' => 'A == B', 'type' => 'bool']],
            'settings' => [],
        ],
        'transform.if' => [
            'group' => 'transform', 'label' => 'If / else', 'icon' => '?',
            'inputs'  => [
                'condition' => ['label' => 'Condition', 'type' => 'bool'],
                'then'      => ['label' => 'Then',      'type' => 'any'],
                'else'      => ['label' => 'Else',      'type' => 'any'],
            ],
            'outputs' => ['value' => ['label' => 'Result', 'type' => 'any']],
            'settings' => [],
        ],

        // ─── Array transforms ──────────────────────────────────────────────
        'transform.first' => [
            'group' => 'transform', 'label' => 'First item', 'icon' => '⏮',
            'inputs'  => ['array' => ['label' => 'Array', 'type' => 'array']],
            'outputs' => ['value' => ['label' => 'First',  'type' => 'any']],
            'settings' => [],
        ],

        // ─── Conversion nodes ──────────────────────────────────────────────
        'convert.to_string' => [
            'group' => 'transform', 'label' => 'To string', 'icon' => '⇒"',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'String', 'type' => 'string']],
            'settings' => [],
        ],
        'convert.to_int' => [
            'group' => 'transform', 'label' => 'To integer', 'icon' => '⇒#',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Integer', 'type' => 'int']],
            'settings' => [],
        ],
        'convert.to_bool' => [
            'group' => 'transform', 'label' => 'To boolean', 'icon' => '⇒?',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Boolean', 'type' => 'bool']],
            'settings' => [],
        ],
        'convert.to_array' => [
            'group' => 'transform', 'label' => 'To array', 'icon' => '⇒[]',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => ['value' => ['label' => 'Array', 'type' => 'array']],
            'settings' => [],
        ],

        // ─── Math ──────────────────────────────────────────────────────────
        'transform.math' => [
            'group' => 'transform', 'label' => 'Math', 'icon' => '∑',
            'inputs'  => [
                'a' => ['label' => 'A', 'type' => 'int'],
                'b' => ['label' => 'B', 'type' => 'int'],
            ],
            'outputs' => ['value' => ['label' => 'Result', 'type' => 'int']],
            'settings' => [
                'op' => [
                    'kind'    => 'select',
                    'label'   => 'Operator',
                    'default' => '+',
                    'options' => ['+' => '+', '-' => '−', '*' => '×', '/' => '÷', '%' => 'mod'],
                ],
            ],
        ],

        // ─── Image pipeline · CSS-filter based, Blender-style ──────────────
        'image.source' => [
            'group'    => 'image',
            'label'    => 'Image source',
            'icon'     => '🖼',
            'inputs'   => [],
            'outputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'url' => ['kind' => 'url', 'label' => 'Image URL', 'default' => 'https://placehold.co/200x140'],
            ],
        ],
        'image.upload' => [
            'group'    => 'image',
            'label'    => 'Image upload',
            'icon'     => '⬆',
            'inputs'   => [],
            'outputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'url' => [
                    'kind'    => 'upload',
                    'label'   => 'Upload an image',
                    'default' => '',
                ],
            ],
        ],
        'image.brightness' => [
            'group' => 'image', 'label' => 'Brightness', 'icon' => '☀',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => 'Brightness (1.0 = normal)', 'default' => '1.0'],
            ],
        ],
        'image.contrast' => [
            'group' => 'image', 'label' => 'Contrast', 'icon' => '◐',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => 'Contrast (1.0 = normal)', 'default' => '1.0'],
            ],
        ],
        'image.saturate' => [
            'group' => 'image', 'label' => 'Saturate', 'icon' => '🎨',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => 'Saturation (1.0 = normal)', 'default' => '1.0'],
            ],
        ],
        'image.grayscale' => [
            'group' => 'image', 'label' => 'Grayscale', 'icon' => '◓',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => '0 = colour · 1 = full grey', 'default' => '1.0'],
            ],
        ],
        'image.sepia' => [
            'group' => 'image', 'label' => 'Sepia', 'icon' => '⌬',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => '0 = normal · 1 = full sepia', 'default' => '1.0'],
            ],
        ],
        'image.invert' => [
            'group' => 'image', 'label' => 'Invert colours', 'icon' => '⇆',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => '0 = normal · 1 = inverted', 'default' => '1.0'],
            ],
        ],
        'image.hue_rotate' => [
            'group' => 'image', 'label' => 'Hue rotate', 'icon' => '🌈',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => 'Degrees', 'default' => '90'],
            ],
        ],
        'image.blur' => [
            'group' => 'image', 'label' => 'Blur', 'icon' => '◌',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => 'Blur radius (px)', 'default' => '4'],
            ],
        ],
        'image.opacity' => [
            'group' => 'image', 'label' => 'Opacity', 'icon' => '○',
            'inputs'  => ['image' => ['label' => 'Image', 'type' => 'image']],
            'outputs' => ['image' => ['label' => 'Image', 'type' => 'image']],
            'settings' => [
                'value' => ['kind' => 'number', 'label' => '0 = invisible · 1 = solid', 'default' => '.65'],
            ],
        ],

        // ─── Annotations · don't participate in evaluation ─────────────────
        'note' => [
            'group'    => 'note',
            'label'    => 'Sticky note',
            'icon'     => '✎',
            'inputs'   => [],
            'outputs'  => [],
            'settings' => [
                'text' => [
                    'kind'    => 'textarea',
                    'label'   => 'Note',
                    'default' => 'A reminder about what this slice of the graph does.',
                ],
            ],
        ],

        // ─── Output ────────────────────────────────────────────────────────
        'output' => [
            'group' => 'output', 'label' => 'Output variable', 'icon' => '▶',
            'inputs'  => ['value' => ['label' => 'Value', 'type' => 'any']],
            'outputs' => [],
            'settings' => [
                'name' => ['kind' => 'text', 'label' => 'Variable name', 'default' => 'newVar'],
            ],
        ],
    ],

    /*
    | Block library · drag-and-drop block types available to the page builder.
    | Each block declares a label, an icon string, and a settings schema. The
    | schema drives the auto-generated settings panel:
    |   - kind: 'text' | 'textarea' | 'select' | 'number' | 'toggle' | 'url'
    |   - label: human label shown above the input
    |   - default: initial value when the block is dropped on the canvas
    |   - options: for kind=select, list of [value => label]
    | Host apps can append more block types via
    |   config(['page-studio.blocks.my-block' => [...]])
    | from a service provider.
    */
    'blocks' => [

        // ─── content blocks · sit inside layout containers or at the page root
        'heading' => [
            'group' => 'content',
            'label' => 'Heading',
            'icon'  => 'H',
            'settings' => [
                'text'  => ['kind' => 'text', 'label' => 'Text', 'default' => 'Section heading'],
                'level' => [
                    'kind'    => 'select',
                    'label'   => 'Level',
                    'default' => 'h2',
                    'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'],
                ],
                'align' => [
                    'kind'    => 'select',
                    'label'   => 'Align',
                    'default' => 'left',
                    'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
                ],
            ],
        ],
        'paragraph' => [
            'group' => 'content',
            'label' => 'Paragraph',
            'icon'  => '¶',
            'settings' => [
                'text' => [
                    'kind'    => 'textarea',
                    'label'   => 'Text',
                    'default' => 'Write your copy here. Drop a {{ variable }} chip from the right to insert a value.',
                ],
            ],
        ],
        'button' => [
            'group' => 'content',
            'label' => 'Button',
            'icon'  => '▭',
            'settings' => [
                'label'   => ['kind' => 'text', 'label' => 'Label', 'default' => 'Continue'],
                'href'    => ['kind' => 'url',  'label' => 'Link URL', 'default' => '#'],
                'variant' => [
                    'kind'    => 'select',
                    'label'   => 'Variant',
                    'default' => 'primary',
                    'options' => ['primary' => 'Primary', 'secondary' => 'Secondary', 'ghost' => 'Ghost'],
                ],
            ],
        ],
        'image' => [
            'group' => 'content',
            'label' => 'Image',
            'icon'  => '🖼',
            'settings' => [
                'src' => ['kind' => 'url',  'label' => 'Image URL', 'default' => 'https://placehold.co/600x300'],
                'alt' => ['kind' => 'text', 'label' => 'Alt text',  'default' => ''],
            ],
        ],
        'list' => [
            'group' => 'content',
            'label' => 'List',
            'icon'  => '☰',
            'settings' => [
                'items' => [
                    'kind'    => 'textarea',
                    'label'   => 'Items (one per line)',
                    'default' => "First item
Second item
Third item",
                ],
                'style' => [
                    'kind'    => 'select',
                    'label'   => 'Style',
                    'default' => 'bullet',
                    'options' => ['bullet' => 'Bulleted', 'number' => 'Numbered'],
                ],
            ],
        ],
        'quote' => [
            'group' => 'content',
            'label' => 'Quote',
            'icon'  => '❝',
            'settings' => [
                'text' => ['kind' => 'textarea', 'label' => 'Quote',  'default' => 'A pithy thing someone said.'],
                'cite' => ['kind' => 'text',     'label' => 'Source', 'default' => ''],
            ],
        ],
        'code' => [
            'group' => 'content',
            'label' => 'Code',
            'icon'  => '⌘',
            'settings' => [
                'code'     => ['kind' => 'textarea', 'label' => 'Code', 'default' => "console.log('hello')"],
                'language' => ['kind' => 'text',     'label' => 'Language', 'default' => 'js'],
            ],
        ],
        'divider' => [
            'group' => 'content',
            'label' => 'Divider',
            'icon'  => '⎯',
            'settings' => [],
        ],
        'spacer' => [
            'group' => 'content',
            'label' => 'Spacer',
            'icon'  => '⇕',
            'settings' => [
                'size' => [
                    'kind'    => 'select',
                    'label'   => 'Size',
                    'default' => 'md',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra large'],
                ],
            ],
        ],

        // ─── layout blocks · accept nested children in named slots
        'section' => [
            'group' => 'layout',
            'label' => 'Section',
            'icon'  => '▢',
            'slots' => ['body' => 'Body'],
            'settings' => [
                'background' => [
                    'kind'    => 'select',
                    'label'   => 'Background',
                    'default' => 'none',
                    'options' => ['none' => 'None', 'tint' => 'Tinted', 'accent' => 'Accent'],
                ],
                'padding' => [
                    'kind'    => 'select',
                    'label'   => 'Padding',
                    'default' => 'md',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'],
                ],
            ],
        ],
        'columns' => [
            'group' => 'layout',
            'label' => '2 Columns',
            'icon'  => '⊟',
            'slots' => ['left' => 'Left', 'right' => 'Right'],
            'settings' => [
                'ratio' => [
                    'kind'    => 'select',
                    'label'   => 'Ratio',
                    'default' => '1-1',
                    'options' => ['1-1' => '50 / 50', '1-2' => '33 / 67', '2-1' => '67 / 33'],
                ],
                'gap' => [
                    'kind'    => 'select',
                    'label'   => 'Gap',
                    'default' => 'md',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'],
                ],
            ],
        ],
        'columns-3' => [
            'group' => 'layout',
            'label' => '3 Columns',
            'icon'  => '⫼',
            'slots' => ['left' => 'Left', 'middle' => 'Middle', 'right' => 'Right'],
            'settings' => [
                'gap' => [
                    'kind'    => 'select',
                    'label'   => 'Gap',
                    'default' => 'md',
                    'options' => ['sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large'],
                ],
            ],
        ],
        'card' => [
            'group' => 'layout',
            'label' => 'Card',
            'icon'  => '⬜',
            'slots' => ['body' => 'Body'],
            'settings' => [
                'title'    => ['kind' => 'text', 'label' => 'Title',    'default' => 'Card title'],
                'subtitle' => ['kind' => 'text', 'label' => 'Subtitle', 'default' => ''],
                'tone'     => [
                    'kind'    => 'select',
                    'label'   => 'Tone',
                    'default' => 'neutral',
                    'options' => ['neutral' => 'Neutral', 'info' => 'Info', 'success' => 'Success', 'warning' => 'Warning'],
                ],
            ],
        ],
        'conditional' => [
            'group' => 'layout',
            'label' => 'If / show when',
            'icon'  => '⊕',
            'slots' => ['body' => 'When true'],
            'settings' => [
                'variable' => [
                    'kind'    => 'text',
                    'label'   => 'Variable name',
                    'default' => 'isAdmin',
                ],
                'mode' => [
                    'kind'    => 'select',
                    'label'   => 'Show when',
                    'default' => 'truthy',
                    'options' => [
                        'truthy' => 'value is truthy',
                        'falsy'  => 'value is falsy / empty',
                        'equals' => 'value equals "compare"',
                    ],
                ],
                'compare' => [
                    'kind'    => 'text',
                    'label'   => 'Compare against',
                    'default' => '',
                ],
            ],
        ],
        'panel' => [
            'group' => 'layout',
            'label' => 'Panel',
            'icon'  => '⊡',
            'slots' => ['body' => 'Body'],
            'settings' => [
                'border' => [
                    'kind'    => 'select',
                    'label'   => 'Border',
                    'default' => 'solid',
                    'options' => ['solid' => 'Solid', 'dashed' => 'Dashed', 'none' => 'None'],
                ],
            ],
        ],
    ],
];