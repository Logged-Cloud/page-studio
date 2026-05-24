# Tutorial · theming the studio (light mode + your own palette)

The studio's chrome (top bar, palettes, drawers, settings panel, overlays) is painted entirely through **seven CSS custom properties**. Override them from your host app's stylesheet and the editor re-skins instantly · no package patches, no recompile, no JS.

The defaults are tuned for a dark-mode editor sitting over a light canvas (so authored pages preview against a realistic white background). Below we walk through the variables, ship a complete light-mode preset, and add `prefers-color-scheme` so the studio follows the user's OS theme.

## The seven variables

| Variable | Default | What it paints |
|---|---|---|
| `--surface` | `#16171a` | Studio background · the canvas surround, the node-editor canvas dot-grid |
| `--surface-2` | `#1E1F22` | Raised surfaces · the top bar, palettes, drawers, node bodies, overlays |
| `--line` | `#3A3D40` | Borders, dividers, button outlines, the line under the topbar |
| `--ink` | `#F0EDE5` | Primary text · button labels, settings input text, headings |
| `--ink-dim` | `#A3A099` | Secondary text · field labels, palette section headings, the "saved 8:50:50 AM" stamp |
| `--accent` | `#2C66E8` | Selection · the highlighted block border, the active palette button, the Save / Publish button background |
| `--danger` | `#ef4444` | Destructive affordances · delete buttons on the block handle |

The **authored content** that lives inside the canvas (the rendered page itself) doesn't read these variables · it ships with its own per-block inline styles so the preview stays consistent across host apps.

## Light-mode preset

Drop this into your host app's main stylesheet (e.g. `resources/css/app.css`):

```css
:root {
    --surface:   #f7f8fa;   /* very light grey · the canvas surround       */
    --surface-2: #ffffff;   /* pure white for the topbar, palettes, drawers */
    --line:      #e2e8f0;   /* light borders                                 */
    --ink:       #0f172a;   /* near-black for primary text                   */
    --ink-dim:   #64748b;   /* slate-500 for secondary text                  */
    --accent:    #2C66E8;   /* keep the brand blue for selection / Save     */
    --danger:    #dc2626;   /* slightly deeper red against the lighter UI    */
}
```

That's it. Refresh and the entire editor flips to a light skin.

> **Tip:** the variables are scoped to `:root` so they cascade everywhere. If you want a single page-builder mount to use a different palette (e.g. a "marketing mode" with a friendlier feel), wrap the Livewire mount in a wrapper element and scope the overrides there:
>
> ```blade
> <div class="ps-studio-light">
>     @livewire('page-studio.page-builder', ['pageId' => $page->id])
> </div>
> ```
>
> ```css
> .ps-studio-light {
>     --surface:   #f7f8fa;
>     --surface-2: #ffffff;
>     --line:      #e2e8f0;
>     --ink:       #0f172a;
>     --ink-dim:   #64748b;
> }
> ```

## Follow the user's OS theme

Wrap both palettes in `@media (prefers-color-scheme)` so the studio matches the user's system setting:

```css
/* Light by default */
:root {
    --surface:   #f7f8fa;
    --surface-2: #ffffff;
    --line:      #e2e8f0;
    --ink:       #0f172a;
    --ink-dim:   #64748b;
    --accent:    #2C66E8;
    --danger:    #dc2626;
}

/* Flip to dark when the OS asks */
@media (prefers-color-scheme: dark) {
    :root {
        --surface:   #16171a;
        --surface-2: #1E1F22;
        --line:      #3A3D40;
        --ink:       #F0EDE5;
        --ink-dim:   #A3A099;
        --danger:    #ef4444;
        /* --accent stays the same · brand colour doesn't flip */
    }
}
```

The author still sees the inside-canvas preview against the same white-page surface either way · only the editor chrome around it changes.

## A few palette ideas

```css
/* Warm light · ivory + slate */
:root {
    --surface:   #faf7f2;
    --surface-2: #ffffff;
    --line:      #e7e0d2;
    --ink:       #1f2937;
    --ink-dim:   #71717a;
    --accent:    #b45309;
    --danger:    #b91c1c;
}

/* High-contrast accessibility */
:root {
    --surface:   #ffffff;
    --surface-2: #ffffff;
    --line:      #000000;
    --ink:       #000000;
    --ink-dim:   #1f2937;
    --accent:    #1d4ed8;
    --danger:    #7f1d1d;
}

/* Branded · pair with your existing host-app design system */
:root {
    --surface:   var(--my-brand-bg);
    --surface-2: var(--my-brand-card);
    --line:      var(--my-brand-border);
    --ink:       var(--my-brand-text);
    --ink-dim:   var(--my-brand-text-dim);
    --accent:    var(--my-brand-primary);
}
```

## Tinted node-editor headers

The bottom drawer's node-editor headers use a few additional hex literals for the per-group tint (sources blue, transforms green, image teal, output rose, notes amber). To re-brand those, target the per-group selectors:

```css
.ps-ne-node[data-node-group="source"]    .ps-ne-node-header { background: #1e40af; }
.ps-ne-node[data-node-group="transform"] .ps-ne-node-header { background: #166534; }
.ps-ne-node[data-node-group="image"]     .ps-ne-node-header { background: #0f766e; }
.ps-ne-node[data-node-group="output"]    .ps-ne-node-header { background: #be123c; }
.ps-ne-node[data-node-group="note"]      .ps-ne-node-header { background: #b45309; }
```

These don't have CSS-variable fallbacks today · use the selectors above for a one-shot rebrand. (If this becomes a common request, the per-group tints will be promoted to variables in a future minor release.)

## What about the rendered page?

The HTML the renderer produces for the actual page (the output side, not the editor chrome) uses inline styles per block · `display:grid`, `background:#fff`, etc. That output is intentionally portable, so host apps can drop the rendered string into any layout without the editor's variables leaking in.

If you want the rendered page to also follow your design system, override the styles in the surrounding Blade view:

```blade
<x-layout>
    <div class="my-host-app-prose">
        {!! \LoggedCloud\PageStudio\Support\PageRenderer::render($page->blocks, $context) !!}
    </div>
</x-layout>
```

```css
.my-host-app-prose h1 { font-family: 'Your-Brand-Font', sans-serif; }
.my-host-app-prose p  { line-height: 1.65; }
.my-host-app-prose .ps-render-btn { background: var(--my-brand-primary); }
```

The renderer is intentionally CSS-class-light · `ps-render-btn` is the only one that sticks, on the Button block · so author-controlled inline styles don't fight you. Override those classes from the host stylesheet and you're done.
