# Tutorial · migrate HTML content into the block tree

Moving off a rich-text editor (CKEditor, TinyMCE, Trix, Summernote, Froala, hand-rolled `contenteditable`) means converting every stored HTML blob into the page-studio block tree. `LoggedCloud\PageStudio\Support\HtmlImporter::toBlocks($html)` does the conversion in one call; this tutorial covers the realistic shape of a one-time backfill, token re-writing, table preservation, and idempotent re-runs.

## What the importer maps

Every block-level HTML tag maps onto a built-in block:

| HTML | Block type | Notes |
|---|---|---|
| `<h1>` – `<h4>` | `heading` | level + text preserved |
| `<p>` | `paragraph` | inline tags inside the paragraph are stripped to their text content |
| `<ul>` / `<ol>` | `list` | one `<li>` per line in the `items` textarea, style picked from the tag |
| `<img>` | `image` | `src` + `alt` carried across |
| `<blockquote>` | `quote` | `text` setting set; `cite` left empty for the author to fill |
| `<pre>` | `code` | language guessed from a `data-language` attribute if present, else empty |
| `<hr>` | `divider` | |
| `<table>` | `table` | raw HTML preserved verbatim in `settings.html` so structure isn't lost |
| `<br>` | (dropped) | bare line breaks aren't a block; nested-tag parsing handles them |
| everything else | `paragraph` | unknown block-level tags fall back to a paragraph with the text content |

Inline tags inside a block (`<a>`, `<strong>`, `<em>`, `<code>`, `<span>`) are stripped to their text. That's a lossy step · the author's intent (a link, a bold word) becomes plain text in the block tree. If your CKEditor blobs lean heavily on inline formatting, plan to re-author those highlights manually after the migration, or write a custom block (e.g. `RichTextBlock`) that preserves the HTML as a single block setting.

## One-time backfill command

The realistic shape is: one artisan command, iterating every row that holds a stored HTML body, writing a sibling `blocks` column with the converted tree. Keep the original HTML column intact during the migration window so you can roll back without data loss.

```php
// app/Console/Commands/ConvertMessagesToBlocks.php
namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use LoggedCloud\PageStudio\Support\HtmlImporter;

class ConvertMessagesToBlocks extends Command
{
    protected $signature = 'app:convert-messages-to-blocks
        {--dry-run : Print the converted block tree counts without writing}';

    protected $description = 'One-time backfill: convert tblMessages.Message HTML into page-studio blocks';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $bar     = $this->output->createProgressBar(Message::count());
        $skipped = 0;
        $written = 0;

        Message::whereNotNull('Message')->chunkById(200, function ($rows) use (&$skipped, &$written, $dryRun, $bar) {
            foreach ($rows as $row) {
                $bar->advance();

                // Idempotent: skip if the row already has a converted tree.
                if (! empty($row->blocks)) {
                    $skipped++;
                    continue;
                }

                $blocks = HtmlImporter::toBlocks((string) $row->Message);
                if (empty($blocks)) {
                    $skipped++;
                    continue;
                }

                if (! $dryRun) {
                    $row->update(['blocks' => $blocks]);
                }
                $written++;
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Wrote $written rows, skipped $skipped.");
        return self::SUCCESS;
    }
}
```

Two things matter for safety:

1. **`chunkById` not `chunk`** · `chunk` re-reads the same rows when a write changes their position; `chunkById` walks the primary key.
2. **Skip rows that already have `blocks`** · re-running the command after a partial failure picks up where it left off without overwriting work that's already been hand-edited.

## Add the `blocks` column

```php
// database/migrations/2026_05_24_000005_add_blocks_to_messages.php
Schema::table('messages', fn ($t) => $t->json('blocks')->nullable()->after('Message'));
```

Cast the column in the model:

```php
class Message extends Model
{
    protected $casts = [
        'blocks' => 'array',
    ];
}
```

## Re-write merge tags

If the existing HTML uses a different merge-tag syntax (CKEditor's Mentions plugin, Mustache `{name}`, `[customer_name]`, etc), convert them to page-studio's `{{ name }}` shape in the same pass:

```php
$html = $row->Message;

// Mustache-style → page-studio
$html = preg_replace('/\{(\w+)\}/', '{{ $1 }}', $html);

// Square-bracket → page-studio
$html = preg_replace('/\[(\w+)\]/', '{{ $1 }}', $html);

// Nested · {customer.name} → {{ customer.name }}
$html = preg_replace('/\{([\w.]+)\}/', '{{ $1 }}', $html);

$blocks = HtmlImporter::toBlocks($html);
```

Run the substitution **before** `HtmlImporter::toBlocks` so the `{{ name }}` tokens land inside the block settings as-is; the renderer's `data_get` lookup will resolve them at render time.

## Mount the page-builder against the new column

If you're keeping per-row block trees (rather than the package's `Page` table), bind the page-builder to your model directly via the ephemeral-mode + saved-event recipe:

```blade
@livewire('page-studio.page-builder', [
    'variables' => [
        'user'    => $recipient,    // Eloquent · flattens to {{ user.name }}, {{ user.email }}, ...
        'program' => $program,
    ],
])

<script>
    Livewire.on('page-studio:page:saved', (e) => {
        fetch('/admin/messages/{{ $message->id }}/blocks', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ blocks: e.blocks }),
        });
    });
</script>
```

On the backend, take the POST and write into your column:

```php
Route::patch('/admin/messages/{message}/blocks', function (Message $message, Request $request) {
    $message->update(['blocks' => $request->input('blocks')]);
    return response()->noContent();
});
```

## Render the converted content

When you eventually send / display, use the renderer instead of dumping the original HTML:

```php
use LoggedCloud\PageStudio\Support\PageRenderer;

$html = PageRenderer::renderForEmail($message->blocks, [
    'user'    => $recipient,
    'program' => $program,
]);
$text = PageRenderer::renderForText($message->blocks, [
    'user'    => $recipient,
    'program' => $program,
]);

Mail::send([], [], fn ($m) => $m->to($recipient)->subject($message->subject)->html($html)->text($text));
```

The renderer handles the dotted-token resolution (`{{ user.email }}` walks `data_get`) so the same content body works for the web AND multipart email outputs.

## Phased rollout

The realistic shape is **a feature-flag period** where both code paths coexist:

```php
public function bodyHtml(User $recipient): string
{
    if (! empty($this->blocks)) {
        return PageRenderer::renderForEmail($this->blocks, $this->context($recipient));
    }
    // Legacy fallback · the HTML body the migration hasn't touched yet
    return $this->Message;
}
```

Drop the legacy branch once the backfill is 100% complete and you've spot-checked a sample.

## Tables · what you get and what you don't

CKEditor's Table plugin output (`<table><tr><th>...</th></tr><tr><td>...</td></tr></table>`) survives verbatim in a `table` block · the raw HTML is stored in `settings.html` and emitted as-is at render time. Tokens inside table cells (`{{ booking.id }}`) still substitute, since the renderer runs the standard substitution pass on the markup.

Authors who want to edit table cells inline switch the block to "Edit HTML" in the right-panel textarea. Future versions of the package may add a structured editor for tables; the raw-HTML approach today is lossless and friction-light.

## Re-running safely

Two safety pieces let you re-run the command without fear:

1. **The "skip if blocks already present" guard** in the conversion loop · already-converted rows are left alone.
2. **`HtmlImporter::toBlocks('')` returns `[]`** · empty rows just skip silently.

If you've spot-corrected some converted block trees by hand, a re-run won't clobber your edits. If you ever do want a full re-conversion, add a `--force` flag to your command that nulls `blocks` first.

## Where to read more

- The importer source: `src/Support/HtmlImporter.php` · ~100 lines, easy to fork if your HTML has quirks (CDATA tags, custom data attributes, etc).
- The test fixtures: `tests/HtmlImporterTest.php` · realistic input shapes including a full CKEditor blob with mixed elements.
- The block-type catalogue: `src/Blocks/Builtin/` · 17 built-ins covering every common HTML shape.
