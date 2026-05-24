# Tutorial · build a starter template

A **Template** seeds a complete route + variables + page + node graph in one shot. Drop a class into `app/PageStudio/Templates/` and authors can install it via `php artisan page-studio:install-template <slug>` · the package auto-discovers + registers it on boot.

The eight built-in templates (blog post, user profile, landing, product, pricing, docs, contact, coming-soon) are exactly this pattern · `src/Templates/Builtin/` is the canonical reference. This tutorial walks a `NewsletterTemplate` end-to-end so you can ship your own library.

## What you'll end up with

```bash
php artisan page-studio:install-template newsletter
# Installed `Newsletter issue` as route `newsletter.show` (id 12)
```

The author then opens the editor for that route and finds a pre-built newsletter layout · hero, intro paragraph, two-column content row, link bar, signature · with `{{ issue.number }}`, `{{ issue.title }}`, `{{ issue.published_at }}` chips already wired into the variable rail.

## 1 · Create the class

```bash
mkdir -p app/PageStudio/Templates
```

```php
// app/PageStudio/Templates/NewsletterTemplate.php
namespace App\PageStudio\Templates;

use LoggedCloud\PageStudio\Templates\Template;

class NewsletterTemplate extends Template
{
    public static function name(): string  { return 'newsletter'; }
    public static function label(): string { return 'Newsletter issue'; }

    public static function description(): string
    {
        return 'Monthly newsletter shape · hero + intro + two-column highlights + signature.';
    }

    public static function route(): array
    {
        return [
            'name'          => 'newsletter.show',
            'method'        => 'GET',
            'path_template' => '/newsletter/{issue}',
            'description'   => 'Monthly newsletter · issue number variable',
            'segments'      => [
                ['position' => 0, 'kind' => 'literal',  'literal_value' => 'newsletter'],
                ['position' => 1, 'kind' => 'variable', 'variable_name' => 'issue'],
            ],
        ];
    }

    public static function variables(): array
    {
        return [[
            'name'        => 'issue',
            'label'       => 'Issue number',
            'type'        => 'int',
            'description' => 'The issue number this newsletter belongs to',
            'examples'    => ['1', '2', '3'],
        ]];
    }

    public static function blocks(): array
    {
        return [
            // Hero · single column, centred.
            self::block('hero', [
                'heading'    => 'Issue {{ issue }}',
                'subheading' => 'A short note from the editor.',
                'image'      => 'https://placehold.co/1200x400',
                'cta_label'  => 'Read past issues',
                'cta_href'   => '/newsletter',
                'align'      => 'center',
            ]),

            self::block('paragraph', [
                'text' => "Welcome to issue {{ issue }}. Two stories below worth your time.",
            ]),

            // Two-column highlights row.
            tap(self::block('columns', ['ratio' => '1-1', 'gap' => 'md']), function (&$c) {
                $c['children'] = [
                    'left' => [
                        self::block('heading',   ['text' => 'Story one',  'level' => 'h3', 'align' => 'left']),
                        self::block('paragraph', ['text' => 'A summary paragraph for the first story.']),
                        self::block('button',    ['label' => 'Read more', 'href' => '#one', 'variant' => 'secondary']),
                    ],
                    'right' => [
                        self::block('heading',   ['text' => 'Story two',  'level' => 'h3', 'align' => 'left']),
                        self::block('paragraph', ['text' => 'A summary paragraph for the second story.']),
                        self::block('button',    ['label' => 'Read more', 'href' => '#two', 'variant' => 'secondary']),
                    ],
                ];
            }),

            self::block('divider'),
            self::block('signature', [
                'signoff' => 'Until next time,',
                'name'    => 'The Editorial Team',
                'title'   => 'newsletter@example.com',
            ]),
        ];
    }
}
```

`tap()` lets you mutate the layout block's `children` after `self::block()` builds the wrapper; the resulting array still looks like every other block-tree node so the page-builder loads it cleanly.

## 2 · Install it

```bash
php artisan page-studio:install-template newsletter
```

Re-runs are idempotent · the command does `updateOrCreate` on route name. Drop the `--rename` flag if you want to seed multiple variations from the same template under different route names:

```bash
php artisan page-studio:install-template newsletter --rename=newsletter.archive
```

## 3 · Anatomy

| Method | Required | What it does |
|---|---|---|
| `name()` | yes | Slug used on the command line. Convention: kebab-case. |
| `label()` | yes | Human label shown in the artisan list view. |
| `description()` | no | One-line summary (also shown in the list). |
| `route()` | yes | `name / method / path_template / description / segments` shape. Mirror an existing built-in to see the segment format. |
| `variables()` | no | Variable rows to upsert before segments are wired. Each is the create payload for `Variable::updateOrCreate(['name' => ...], $payload)`. |
| `blocks()` | no | Block tree to save into the Page row. Use `self::block($type, $settings)` to mint a fresh block; mutate `$b['children'][$slot]` for layout blocks. |
| `graph()` | no | Node graph `['nodes' => [...], 'edges' => [...]]`. Same shape page-studio persists; usually easier to copy from an existing built-in or hand-author by reading `src/Models/NodeGraph.php`. |

## 4 · Add a node graph

If the template needs to compose variables (e.g. format the published_at field, slugify the title), add a `graph()` override:

```php
public static function graph(): array
{
    return [
        'nodes' => [
            ['id' => 'src',  'type' => 'source.route_variable', 'position' => ['x' => 80,  'y' => 80],
                'settings' => ['variable_name' => 'issue']],
            ['id' => 'pad',  'type' => 'transform.concat',       'position' => ['x' => 320, 'y' => 80],
                'settings' => ['separator' => '']],
            ['id' => 'src2', 'type' => 'source.constant',        'position' => ['x' => 80,  'y' => 180],
                'settings' => ['value' => 'Issue #']],
            ['id' => 'out',  'type' => 'output',                 'position' => ['x' => 600, 'y' => 100],
                'settings' => ['name' => 'issueLabel']],
        ],
        'edges' => [
            ['id' => 'e1', 'from_node' => 'src2', 'from_socket' => 'value', 'to_node' => 'pad', 'to_socket' => 'a'],
            ['id' => 'e2', 'from_node' => 'src',  'from_socket' => 'value', 'to_node' => 'pad', 'to_socket' => 'b'],
            ['id' => 'e3', 'from_node' => 'pad',  'from_socket' => 'value', 'to_node' => 'out', 'to_socket' => 'value'],
        ],
    ];
}
```

After install, `{{ issueLabel }}` is available as a chip and resolves to `"Issue #1"`, `"Issue #2"`, etc. The position coordinates are picked from looking at where you'd lay them out by hand in the editor; the engine's Tidy button can later auto-arrange.

## 5 · Register from a sub-namespace

Auto-discovery walks `app/PageStudio/Templates/` recursively. To pull from elsewhere:

```php
// config/page-studio.php
'template_paths' => [
    ['dir' => app_path('PageStudio/Templates'),     'namespace' => 'App\\PageStudio\\Templates'],
    ['dir' => app_path('Marketing/Templates'),       'namespace' => 'App\\Marketing\\Templates'],
],
```

Or register explicitly:

```php
use LoggedCloud\PageStudio\Templates\TemplateRegistry;

TemplateRegistry::register(\App\PageStudio\Templates\NewsletterTemplate::class);
```

## 6 · Verify

A simple Pest test next to the class:

```php
use App\PageStudio\Templates\NewsletterTemplate;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Templates\TemplateRegistry;
use Illuminate\Support\Facades\Artisan;

it('newsletter template installs route + variables + page', function () {
    TemplateRegistry::register(NewsletterTemplate::class);

    Artisan::call('page-studio:install-template', ['name' => 'newsletter']);

    $route = RouteDefinition::where('name', 'newsletter.show')->first();
    expect($route)->not->toBeNull()
        ->and($route->path_template)->toBe('/newsletter/{issue}')
        ->and(Variable::where('name', 'issue')->exists())->toBeTrue();
});
```

## 7 · Discover what shapes work

The eight built-ins under `src/Templates/Builtin/` cover the common patterns:

| Template | Pattern it shows |
|---|---|
| `BlogPostTemplate` | Single variable route + content stack |
| `UserProfileTemplate` | Variable + model-finder node graph populating chips |
| `LandingTemplate` | Literal-only route + 3-column hero layout |
| `ProductDetailTemplate` | Variable route + multi-section product page |
| `PricingTemplate` | Literal route + 3-tier columns with cards |
| `DocsTemplate` | 1-2 columns ratio for sidebar + body, with a code block |
| `ContactTemplate` | Section + nested panel |
| `ComingSoonTemplate` | Spacer + centred CTA, minimal copy |

When in doubt, copy the closest one and adjust.
