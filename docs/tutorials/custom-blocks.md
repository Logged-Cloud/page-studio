# Tutorial · build a custom block

Page-studio's block palette is open. Drop a PHP class into your host app's `app/PageStudio/Blocks/` directory and the package picks it up at boot · no migrations, no config files, no Livewire wiring.

In this walkthrough we'll build a **CalloutBlock** that authors use for "info / warning / danger" callouts, render it three ways (web, email, plain text), and gate it via email mode.

## What you'll end up with

```blade
{{-- The block renders like this on the web --}}
<aside class="callout callout--warning">
    <strong>Heads up:</strong> the late fee starts at midnight.
</aside>
```

## 1 · Create the class

```bash
mkdir -p app/PageStudio/Blocks
```

```php
// app/PageStudio/Blocks/CalloutBlock.php
namespace App\PageStudio\Blocks;

use LoggedCloud\PageStudio\Blocks\BlockType;
use LoggedCloud\PageStudio\Support\PageRenderer;

class CalloutBlock extends BlockType
{
    public static function key(): string   { return 'callout'; }
    public static function label(): string { return 'Callout'; }
    public static function icon(): string  { return '⚠'; }
    public static function group(): string { return 'content'; }

    public static function settings(): array
    {
        return [
            'tone' => [
                'kind'    => 'select',
                'label'   => 'Tone',
                'default' => 'info',
                'options' => ['info' => 'Info', 'warning' => 'Warning', 'danger' => 'Danger'],
            ],
            'title' => [
                'kind'    => 'text',
                'label'   => 'Title',
                'default' => 'Heads up',
            ],
            'body' => [
                'kind'    => 'textarea',
                'label'   => 'Body',
                'default' => 'A line or two explaining what the reader needs to know.',
            ],
        ];
    }

    public function render(array $settings, array $children, array $context, bool $decorate = false): string
    {
        $tone  = $settings['tone'] ?? 'info';
        $title = PageRenderer::renderText((string) ($settings['title'] ?? ''), $context, $decorate);
        $body  = PageRenderer::renderText((string) ($settings['body']  ?? ''), $context, $decorate);

        $palette = match ($tone) {
            'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b'],
            'danger'  => ['bg' => '#fef2f2', 'border' => '#ef4444'],
            default   => ['bg' => '#eff6ff', 'border' => '#3b82f6'],
        };

        return sprintf(
            '<aside class="callout callout--%s" style="background:%s;border-left:4px solid %s;padding:.9rem 1.1rem;border-radius:.4rem;margin:.65em 0">'
                .'<strong>%s</strong> %s</aside>',
            $tone, $palette['bg'], $palette['border'], $title, $body,
        );
    }
}
```

Drop the file, reload the page-builder · the **Callout** chip appears in the Content section of the block palette.

## 2 · Anatomy

| Method | Required | What it controls |
|---|---|---|
| `key()` | yes | Identifier saved in the block tree. Convention: short slug. |
| `label()` | yes | Human-readable name shown in the palette + tree views. |
| `icon()` | no | Glyph for the palette button. Defaults to `◻︎`. |
| `group()` | no | `'content'` or `'layout'`. Drives which palette section the block lands in. |
| `settings()` | no | Per-instance settings rendered into the right panel. Supports `text` / `textarea` / `url` / `select` / `number` / `bool` / `upload`. |
| `slots()` | no | Named child slots for layout blocks. Empty for content blocks. |
| `render()` | yes | The HTML emitted on the web. Receives settings, children (slot map), the page's variable context, and a `decorate` flag the editor uses for inline preview chips. |

## 3 · Make it email-safe

If your block uses CSS grid, `color-mix`, or anything Outlook strips, override `renderEmail()` and emit a table-based version. Authors can keep the same block on the canvas; the email renderer picks the safe path automatically.

```php
public static function emailSafe(): bool { return true; }

public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
{
    $tone  = $settings['tone'] ?? 'info';
    $title = PageRenderer::renderText((string) ($settings['title'] ?? ''), $context, false);
    $body  = PageRenderer::renderText((string) ($settings['body']  ?? ''), $context, false);

    $palette = match ($tone) {
        'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b'],
        'danger'  => ['bg' => '#fef2f2', 'border' => '#ef4444'],
        default   => ['bg' => '#eff6ff', 'border' => '#3b82f6'],
    };

    // Two-cell table · narrow cell paints the accent stripe, wide cell holds the copy.
    return sprintf(
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%%" style="margin:10px 0;border-collapse:collapse">'
            .'<tr>'
                .'<td width="4" bgcolor="%2$s" style="width:4px;background:%2$s">&nbsp;</td>'
                .'<td bgcolor="%1$s" style="background:%1$s;padding:12px 16px;font-family:-apple-system,system-ui,sans-serif">'
                    .'<strong>%3$s</strong> %4$s'
                .'</td>'
            .'</tr>'
        .'</table>',
        $palette['bg'], $palette['border'], $title, $body,
    );
}
```

Set `emailSafe()` to `false` when there's no email-friendly rendering · the block is hidden from the palette in email mode but existing instances still render on the web.

## 4 · Plain-text counterpart

Multipart emails need a text/plain body too. Override `renderText()`:

```php
public function renderText(array $settings, array $children, array $context): ?string
{
    $title = PageRenderer::substitute((string) ($settings['title'] ?? ''), $context);
    $body  = PageRenderer::substitute((string) ($settings['body']  ?? ''), $context);
    return "{$title}: {$body}\n\n";
}
```

Returning `null` falls back to stripping HTML from `render()`, which is fine for simple blocks but loses structure for tables, lists, layouts.

## 5 · Use variables

Anywhere a setting contains `{{ name }}` or `{{ user.email }}`, call `PageRenderer::renderText($value, $context, $decorate)` for body copy (escapes HTML) or `PageRenderer::substitute($value, $context)` for attribute values (no escaping).

```php
$body = PageRenderer::renderText('Hi {{ user.name }}, the booking is for {{ booking.dates }}.', $context, false);
```

The host app passes `user` and `booking` into the page-builder via the `variables` prop · the renderer flattens Eloquent models to dot-paths automatically.

## 6 · Layout blocks · slots

Add `slots()` to take child blocks in named regions:

```php
public static function slots(): array
{
    return ['header' => ['label' => 'Header'], 'body' => ['label' => 'Body']];
}

public function render(array $settings, array $children, array $context, bool $decorate = false): string
{
    $header = PageRenderer::renderChildren($children, 'header', $context, $decorate);
    $body   = PageRenderer::renderChildren($children, 'body',   $context, $decorate);
    return "<section><header>{$header}</header>{$body}</section>";
}

// For email rendering, use renderChildrenForEmail() instead so nested
// blocks pick their own email renders too.
public function renderEmail(array $settings, array $children, array $context, bool $decorate = false): ?string
{
    $body = PageRenderer::renderChildrenForEmail($children, 'body', $context, $decorate);
    return "<table><tr><td>{$body}</td></tr></table>";
}
```

## 7 · Register from a sub-namespace

Auto-discovery walks `app/PageStudio/Blocks/` recursively by default. To pull from elsewhere, override the path:

```php
// config/page-studio.php
'block_paths' => [
    ['dir' => app_path('PageStudio/Blocks'),       'namespace' => 'App\\PageStudio\\Blocks'],
    ['dir' => app_path('Domain/Marketing/Blocks'), 'namespace' => 'App\\Domain\\Marketing\\Blocks'],
],
```

Or register explicitly from a service provider:

```php
use LoggedCloud\PageStudio\Blocks\BlockRegistry;

BlockRegistry::register(\App\PageStudio\Blocks\CalloutBlock::class);
```

## 8 · Verify

Add a Pest test next to the class for the renders:

```php
use App\PageStudio\Blocks\CalloutBlock;

it('callout web render emits an aside with the tone class', function () {
    $block = new CalloutBlock();
    $html  = $block->render(
        ['tone' => 'warning', 'title' => 'Heads up', 'body' => 'Pay now.'],
        [], [],
    );
    expect($html)->toContain('callout--warning')
        ->and($html)->toContain('Heads up')
        ->and($html)->toContain('Pay now.');
});

it('callout email render uses a table layout', function () {
    $block = new CalloutBlock();
    $html  = $block->renderEmail(['tone' => 'info', 'title' => 'FYI', 'body' => 'Heads up.'], [], []);
    expect($html)->toContain('<table')
        ->and($html)->toContain('bgcolor="#eff6ff"');
});
```

That's the whole loop. Built-in blocks under `src/Blocks/Builtin/` are exactly this shape · read any of them for further patterns (`CardBlock` for a table-based card, `ColumnsBlock` for slot recursion, `TableBlock` for free-form HTML preservation).
