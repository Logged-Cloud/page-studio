# Changelog

## v2.12.3 · 2026-07-13

### Fixed

- **Card block text visibility on dark-themed pages** — the card block's title and body inherited the page's ambient text colour rather than a colour pinned to its own light background, so both went invisible whenever a card landed on a dark-themed page. The block now pins a dark text colour on its own container (web + email renders), independent of the host page's theme.

## v1.0.0 · 2026-05-24

First public release.

### What you get

- **Route builder** — author URLs in a chip editor, right-click any segment to turn it into a reusable variable with type, regex, validation rule, and example values.
- **Page builder** — Shopify-style drag-and-drop authoring with content + layout blocks, slot nesting, inline variable chips, multi-device preview, autosave, revisions, undo / redo.
- **Node editor** — Blender-style graph for composing new variables from route values, model lookups, transforms, math, image filters, conversions, and developer-defined custom nodes.
- **Code-defined custom nodes + blocks** — drop a class into `app/PageStudio/Nodes/` or `app/PageStudio/Blocks/`, extends the appropriate base class, and the package auto-registers it on boot.
- **Starter templates** — 8 built-ins (blog post, user profile, landing, product, pricing, docs, contact, coming soon) installable via `php artisan page-studio:install-template <slug>`.
- **Model FQCN dropdown** — `php artisan page-studio:discover-models` caches the host app's Eloquent model list so the Model finder node renders as a populated select.
- **Standalone mode** — `@livewire('page-studio.page-builder', ['variables' => [...]])` lets you use the page-builder without binding to a route.
- **Mobile-ready** — viewports under 768px collapse the rails to slide-in sheets, hide the node drawer by default, and give the canvas the screen.
- **In-page finder** — Ctrl-F or `/` opens a unified search palette over the block tree + node graph.
- **Auto-registered routes** — every saved `RouteDefinition` becomes a real Laravel route serving the authored page.
- **Permissions gate** — set `config('page-studio.gate')` and every Livewire mount calls `Gate::allows()`.
- **Events** — `RouteSaved`, `PageSaved`, `GraphSaved` fire on each persist so host apps can audit / invalidate caches.

### Testing

Ships with **187 Pest** tests (release-tested against Orchestra Testbench 9 + 10) and a sibling lab project that runs **43 Dusk** end-to-end browser tests against the live editor.

### Licence

Released under the Fair Source License 1.1 (FSL-1.1-MIT). Internal use, non-commercial education + research, and professional services are permitted. Reselling the software as a competing product or service is not. The licence converts to MIT two years after release.
