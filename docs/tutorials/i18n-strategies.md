# Internationalisation strategies

page-studio doesn't ship a multi-locale story out of the box · a block has one set of fields, period. That's a deliberate choice: i18n at the *block* level adds a lot of editor complexity for use cases that don't need it, and host apps that DO need it usually already have an i18n strategy they want the studio to respect.

This tutorial covers the three shapes that work today.

## Strategy 1 · One Page row per locale

The pattern most marketing sites land on. Simplest to reason about, costs disk space in proportion to locale count.

```php
// migration
Schema::create('localized_pages', function (Blueprint $t) {
    $t->id();
    $t->string('slug');
    $t->string('locale', 5);
    $t->foreignId('page_id');   // FK to page_studio_pages
    $t->timestamps();
    $t->unique(['slug', 'locale']);
});
```

Mount the page-builder against a different `page_id` per locale:

```php
Route::get('/{locale}/{slug}/edit', function (string $locale, string $slug) {
    $row = LocalizedPage::where('slug', $slug)->where('locale', $locale)->firstOrFail();
    return view('page-edit', ['pageId' => $row->page_id]);
});
```

**Pros:** every locale gets its own block tree, so authors can adapt structure (not just text) per locale · e.g. a German layout with longer headlines, a Japanese layout that drops the hero image.

**Cons:** changes don't propagate. If you fix a typo in the English page, the French/German/Japanese copies are unchanged · editors do the work N times.

## Strategy 2 · One Page, locale-aware variable context

The page tree is one shared structure; the locale-specific text comes from the variable context the host app passes in.

```php
// in the host app's render path
$page    = Page::find($pageId);
$context = trans()->get('pages.'.$page->slug);   // [hero_heading => '...', hero_body => '...']

echo PageRenderer::render($page->blocks, $context);
```

The page tree's text fields use `{{ hero_heading }}` tokens instead of literal copy:

```
[heading] {{ hero_heading }}
[paragraph] {{ hero_body }}
[button] {{ cta_label }} → {{ cta_href }}
```

Translation lives in your existing Laravel `lang/*` files (or a database, or a translation service · whatever your host app already uses).

**Pros:** one source of truth for layout · a structural change in English ships to every locale on next render. Translators work in the systems they already know (Phrase, Lokalise, Crowdin, plain JSON files).

**Cons:** authors lose the WYSIWYG benefit · they're editing tokens, not the actual copy. Works best when the page schema is fixed and the translators are a separate team.

## Strategy 3 · Hybrid · layout shared, text in node graph

Use the node editor to source per-locale text via a `source.model_finder` against your translations table:

```
source.route_variable[locale] ──┐
                                ├─→ source.model_finder[Translation, slug=hero_heading, expose_fields=true]
constant[hero_heading]    ──────┘
                                └─→ value → output[hero_heading]
```

The page consumes the variables the graph emits; the graph swaps out values based on the `locale` route segment.

**Pros:** layout shared like strategy 2, but the studio's own data layer holds translations · no separate `lang/` files to keep in sync with editor changes.

**Cons:** larger graphs (one variable per text field). Worth it when translators want a friendly UI inside the studio's own admin.

## Picking between them

| You need…                                        | Strategy |
| ------------------------------------------------ | -------- |
| Per-locale layout freedom, small N (3-5 locales) | 1        |
| Many locales (10+), structural parity            | 2        |
| Translators editing inside the studio admin      | 3        |
| RTL layouts (Arabic, Hebrew)                     | 1 (RTL blocks can be local-only) |

## RTL specifics

The studio's chrome is LTR but the rendered output respects whatever `dir=""` attribute the host layout sets. If your RTL locale needs different block ordering (e.g. visual reading order changes), strategy 1 is the cleanest fit.

```blade
<html lang="{{ $locale }}" dir="{{ in_array($locale, ['ar','he','fa']) ? 'rtl' : 'ltr' }}">
```

## Locale-aware permalinks

The render cache (if enabled) keys on the block tree + variable context. Different `locale` in the context = different sha1 = separate cache entry. No special handling needed.

## What's NOT shipped

- Inline locale switcher in the editor (would let an author toggle between EN / FR copy on the same canvas)
- Auto-translation buttons
- Locale-fallback chains (if FR is empty, fall back to EN)

If you need any of those, layer them in the host app. The render-cache + the variable context together give you most of what's needed; the missing piece is editor UX, which is your call about how invasive it should be.
