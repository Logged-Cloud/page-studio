# Deploying to production

What to wire up before pointing real traffic at a host app that mounts page-studio. None of this is unique to the studio · these are the same Laravel production hygiene boxes every app should tick, gathered in one place for the bits that interact with the page-builder.

## Cache the views

The page-builder is one big Blade template (~2200 lines + a fat `@push('scripts')` block). The first compile takes 100-200 ms; subsequent renders serve the compiled view in microseconds.

```bash
php artisan view:cache
```

Add it to your deploy script. Re-run on every release that touches the package or the host app's own views.

## Decide on the render cache

The cache is **off by default**. It buys nothing on content-only block trees (heading / paragraph / button / image / list / quote / columns / hero) because rendering is already faster than the cache lookup itself.

Turn it on when:

- A custom block hits the database in `render()` (e.g. a `<latest-blog-posts limit=3>` block).
- A custom block does HTTP fetches or image transforms at render time.
- Page render is on the critical path of a request that's noticeably slow.

```bash
# .env
PAGE_STUDIO_RENDER_CACHE=true
PAGE_STUDIO_RENDER_CACHE_TTL=3600
PAGE_STUDIO_RENDER_CACHE_STORE=redis   # optional · defaults to the app's default store
```

No active invalidation is needed: any change to the block tree or variable context yields a different sha1, misses the cache, and recomputes. Stale entries age out via TTL.

See [docs/performance.md](../performance.md) for measured numbers and a "is the cache worth it for me" rule of thumb.

## Queue worker · only if you use jobs

The base package doesn't ship any queued jobs, but if your host app dispatches work from page-publish events (Slack notifications, sitemap regeneration, etc.), run a worker:

```bash
php artisan queue:work --tries=3 --max-time=3600
```

Supervise it with systemd, a Docker `restart: unless-stopped`, or Laravel Horizon. Restart on each deploy so the worker picks up new code:

```bash
php artisan queue:restart
```

## Migrations and the safety guard

The studio's `RefreshDatabase`-style integration tests will wipe whatever database the test process is pointed at. The package ships a `Tests\TestCase` that refuses to boot if `DB_CONNECTION` isn't `sqlite` and `DB_DATABASE` isn't `:memory:`. Keep it.

A host app that runs tests inside a Docker container with production-shaped env vars (e.g. `DB_CONNECTION=mysql`) needs `phpunit.xml` to double every `<env>` tag as a `<server>` tag with `force="true"`. Without this, Laravel's `env()` reads `$_SERVER` first and the test process talks to production. See the [test-DB safety pattern](https://github.com/Logged-Cloud/page-studio/blob/main/tests/TestCase.php) the package itself uses.

## Trust the proxy

If you sit behind Caddy / Cloudflare / a load balancer, set:

```php
// bootstrap/app.php
$middleware->trustProxies(at: '*');
```

Without this, Laravel sees `http://` from the upstream and generates http:// redirects, which the proxy then rewrites or breaks.

## Schedule

If you use `studio:export-static-site` or any custom command that consumes block data, add a host cron entry that runs `php artisan schedule:run` every minute. The package's own scheduled work (none today, beyond what your host app adds) hooks into that.

```
* * * * * docker exec <app-container> php /var/www/html/artisan schedule:run >> /dev/null 2>&1
```

## Symlink storage

The image block reads from `public/storage/...` by default · run `php artisan storage:link` so uploaded images resolve.

## Smoke-test after deploy

A minimal post-deploy check:

```bash
curl -sf https://your-app.example.com/health || exit 1
curl -sf https://your-app.example.com/your/page-builder/route > /dev/null || exit 1
```

If either route returns non-200, fail the deploy.

## Roll-back plan

The studio stores all state in three tables (`page_studio_pages`, `page_studio_routes`, `page_studio_revisions`). The Revisions table keeps the last 30 per route, so the host app can roll back a bad save without leaning on database backups:

```php
$page->revisions()->latest('id')->first()?->restore();
```

For a wider blast-radius rollback (a bad deploy), normal Laravel-app rollback steps apply · revert the code, replay migrations down, restart workers, clear view + config caches.
