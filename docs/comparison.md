# How page-studio compares

A short, honest walk through where `logged-cloud/page-studio` sits in the page-builder / headless-CMS landscape. The goal is to help you pick the right tool, not sell you ours by hiding the trade-offs.

## TL;DR

| You want… | Best fit |
| --- | --- |
| A visual page builder that lives **inside** a Laravel + Livewire app, no external service | **page-studio** |
| A standalone CMS your editorial team logs into separately | Statamic, Twill, Strapi, Sanity |
| Code-first form / admin scaffolding, not free-form page authoring | Filament, Nova |
| A no-code SaaS page builder hosted by someone else | Webflow, Framer, Builder.io |
| A WordPress-style block editor for content marketing | Gutenberg |

Page-studio is opinionated about one specific shape: **your Laravel app already exists, you want non-developers to compose pages + routes + variables inside it, and you don't want to operate a second service**. Anything that doesn't match that shape, one of the alternatives below is probably a better fit.

---

## vs. Filament Form Builder

[Filament](https://filamentphp.com/) is the obvious comparison in the Laravel ecosystem, but the products solve different problems.

- **Filament Builder** is a *form field*. The author edits a list of preconfigured block types inside a Filament form. It's excellent for "the marketing team adds a new hero + features list to this static page" and the schema is defined in PHP.
- **page-studio** is a *standalone editor*: drag-and-drop canvas, nested layout slots, a node-graph variable editor, route binding, presence + comments + activity feed. The output is a block tree you store anywhere (a JSON column, a Page model, a Redis blob), not a Filament resource row.

Pick Filament if: you already run Filament, the page schema is fixed, and the editor lives inside an admin form. Pick page-studio if: you want a real canvas, a variable layer, route authoring, or collaboration features; or you don't run Filament at all.

You can also use both side by side · Filament for tabular admin, page-studio for the page-level authoring surface.

## vs. Statamic / Twill / Strapi / Sanity

These are full content management systems · separate admin app, separate database, content delivered to your Laravel app via templates, GraphQL, or REST.

- They win when **content is the product**: a marketing site, a magazine, a documentation portal. Editorial workflows, multi-environment publishing, asset libraries, internationalisation, role-based permissions across hundreds of users.
- They lose when **the app is the product** and you just want some pages inside it editable. Running a parallel CMS to author 3 landing pages and an FAQ is a lot of operational overhead for a small surface area.

Page-studio is deliberately not a CMS. It has no separate admin, no asset library, no editorial workflow beyond draft / published / scheduled. It runs as a Composer package inside your existing app.

## vs. Voyager / Backpack / Nova

These are admin scaffolders · CRUD generators on top of Eloquent. They don't aim to build pages from blocks; they aim to build admin tables and forms from models.

Different category. They're not really competitors so much as adjacent tools you'd run alongside page-studio.

## vs. WordPress / Gutenberg

Gutenberg is mature, has a huge block ecosystem, and is the default page-building experience on a vast chunk of the internet.

Page-studio wins on:
- **Laravel-native integration** · variables read from your routes, blocks render through your Blade views, events flow through Laravel's dispatcher.
- **Code-defined blocks + nodes** · extend the registry with a PHP class, not a JS plugin in a PHP CMS.
- **Smaller surface area** · no theme system to learn, no plugins to vet, no separate user role model.

Gutenberg wins on:
- **Editorial maturity** · meta boxes, reusable blocks, patterns, taxonomies, asset management.
- **Ecosystem** · thousands of blocks and themes you can drop in.
- **Headless CMS use** · WordPress as a back-end for a separate front-end is a well-trodden path.

If you're already on WordPress, stay there. If you're building a Laravel product and need *some* page authoring without leaving the stack, page-studio is the smaller commitment.

## vs. Builder.io / Webflow / Framer

Closed-source visual builders hosted by someone else. They're polished, opinionated, and have great editor UX.

The trade-off is the obvious one: your content lives on their service, your blocks render through their SDK, and your editor lives at their URL. If you can live with that, the editor experience is hard to beat.

Page-studio is the opposite trade-off: less polish, but the data lives in your DB, the renderer is `PageRenderer::render($blocks)`, and the editor lives at `/your-app/pages/123/edit`.

---

## What page-studio gives up

In the spirit of honesty:

- **No asset library.** Image fields take a URL string. Plugging in a media library is a couple of lines but isn't shipped by default · we don't want to fight an existing one in your host app.
- **No multi-environment publishing.** A "publish" sets a status flag. If you want staged content (write in dev, promote to prod), the existing Revisions table gets you 80% of the way but the last 20% is your problem.
- **No i18n out of the box.** A block has one set of fields. Multi-locale pages are usually handled at the *page row* level (one row per locale) rather than per-block translations.
- **No editorial role management beyond what the host app provides.** The Gate name in the config either allows or refuses; if you need fine-grained "Alice can edit hero blocks but not pricing", you wrap the publish action in your own policy.
- **No CDN-style edge rendering.** The render cache is opt-in and stores in your configured Laravel cache · CDN-edge rendering is out of scope.

If any of those are deal-breakers, a full CMS will serve you better.

## What page-studio gives you

- **Drop-in install.** `composer require`, one migration, mount a Livewire component. No second service to operate.
- **Code-defined blocks + nodes + templates.** Extend the registry with a PHP class; appears in the palette next reload.
- **Real-time-ish collaboration.** Block locks, presence chips, review threads, activity feed · all on short-poll HTTP, no WebSocket dependency.
- **Variables as a first-class concept.** Route variables, model lookups, transformation graphs, dot-notation context · the page knows what data it's rendering against.
- **Email + plain-text + markdown rendering** as first-class output modes, not just HTML.
- **FSL licensing.** Commercial-friendly out of the gate, converts to MIT after two years. See the [License section](../README.md#license).

---

## Quick decision tree

```
Do you already have a Laravel app you want pages inside?
├─ No  → use Statamic, Strapi, Sanity, or Gutenberg.
└─ Yes
    ├─ Is the page schema fixed and admin-form-shaped?
    │   └─ Yes → use Filament Form Builder.
    └─ Do you want a real canvas + variables + collaboration?
        └─ Yes → page-studio.
```

When in doubt, install it (`composer require logged-cloud/page-studio`), mount the page-builder in a sandbox route, and try it for an hour. The studio is small enough that an hour is enough time to know whether it fits.
