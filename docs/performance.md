# Performance notes

The render path is **fast**. A 50-section synthetic page (150 blocks: heading + paragraph + button × 50) renders in roughly **0.8 ms** on a 2026-era PHP 8.4 cli runner. The numbers scale linearly:

| Blocks | Render (no cache) | Per block |
| -----: | ----------------: | --------: |
|    150 |          0.8 ms   |  ~5 µs    |
|    600 |          2.1 ms   |  ~3 µs    |
|   3000 |         10.7 ms   |  ~3 µs    |

The benchmark lives at `bench/render.php`. Run it from the package root:

```bash
docker run --rm -v $PWD:/work -w /work php:8.4-cli \
    php bench/render.php 200    # 200 sections, 600 blocks
```

## When does the render cache help?

For trees of typical content blocks (heading, paragraph, button, list, quote, image, columns, hero, panel), the render path is already faster than the cache lookup itself. Turning the cache on doesn't make things noticeably faster, and can be marginally slower on hot pages because the cache key has to be computed before the early-return.

The cache is worth turning on when a block's `render()` does meaningful work · DB lookups inside a custom block, image transforms, HTTP fetches resolved at render time. Those kinds of blocks can take 100s of ms each. Caching skips that work for repeat renders with the same context.

Rule of thumb: turn it on if your custom blocks talk to the network or a database, leave it off if your blocks just push HTML.

## Where the real bottlenecks live (not the renderer)

If the editor feels slow, the renderer is almost never the cause. The places to look first:

- **Livewire round-trip latency.** Every save / publish / drag-and-drop is a network call. Slow shared hosting or heavy session middleware will dominate the editor experience long before the renderer matters.
- **Collab pollers.** Each open tab heartbeats every ~8 seconds for presence + block locks + activity feed. Three open tabs on one page = roughly 22 queries per minute just for collab. Bounded by `BlockLock::active()` + `Activity::recent(30)` + presence updateOrCreate, all indexed. Fine for normal-sized teams. Heavy multi-tenant deployments may want to swap the polling for Reverb / Pusher or raise the heartbeat interval.
- **Page-builder Blade compilation.** ~2200 lines of Blade + a big `@push('scripts')` block · the first request after `view:cache` clear can take 150 ms to compile. Subsequent requests serve the compiled view. Run `php artisan view:cache` in production deploys.

## Measuring real workloads

`bench/render.php` is the floor. For real-world numbers under a real Laravel app, drop a few `microtime(true)` markers around the page-builder route handler:

```php
$start = microtime(true);
$html = PageRenderer::render($page->blocks, $context);
\Log::debug('page render', ['ms' => (microtime(true) - $start) * 1000, 'blocks' => count($page->blocks)]);
```

If a real page comes in noticeably above the benchmark's ~3 µs/block, the suspect is almost always a custom block doing work in `render()` · profile that block in isolation before reaching for the cache.
