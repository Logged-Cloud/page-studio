# Author names in presence chips, lock ribbons, and the activity feed

When two people edit the same page, page-studio surfaces three pieces of identity:

- **Presence chips** in the topbar — initials per peer, full name on hover.
- **Block-lock ribbon** — "🔒 Alice editing · Heading text".
- **Activity feed + comment threads** — "Alice saved the page", "Bob commented on…".

All three read the author's display name through one helper inside the page-builder. The resolution chain is deliberately simple:

```
$user->name     ↦ if set, used directly
↓
$user->email    ↦ fallback when name is empty
↓
'User <id>'     ↦ last-resort placeholder
```

For most Laravel apps that's enough · the default `App\Models\User` ships with a `name` column. But host apps with a non-standard User shape (no `name` column, split first/last fields, a `display_name` override, SSO-mapped attributes) need to wire the name resolution themselves.

## Option 1 · Eloquent accessor on User (preferred)

The cleanest fix. Add a `name` accessor to your User model that returns whatever your app considers the display name:

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Authenticatable
{
    // No `name` column · the model has first_name + last_name.
    protected function name(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name) ?: $this->email);
    }
}
```

Page-studio reads `$user->name`, the accessor fires, the right string lands in the lock ribbon + presence chip + activity feed. No package change required.

### Variations

```php
// Pull from a profile relation.
protected function name(): Attribute
{
    return Attribute::get(fn () => $this->profile?->display_name ?? $this->email);
}

// Pick the first non-empty of several candidates.
protected function name(): Attribute
{
    return Attribute::get(function () {
        return $this->display_name
            ?: trim($this->given_name.' '.$this->family_name)
            ?: $this->username
            ?: $this->email;
    });
}

// Map an SSO claim cached on the model.
protected function name(): Attribute
{
    return Attribute::get(fn () => $this->sso_claims['preferred_username'] ?? $this->email);
}
```

## Option 2 · A `name` column even if you don't store it

If you want a literal column-shaped value but compute it once on save:

```php
// In a model boot() or an observer.
static::saving(function (User $u) {
    $u->name = trim($u->first_name.' '.$u->last_name);
});
```

That makes `$user->name` a real database value other tools can read too · the package treats it identically.

## Option 3 · Snapshot at lock / comment time

The block-locks, presence, and activity-feed rows each carry their own `author_name` column. Page-studio writes the name at the moment the row is created · so even if a user later renames themselves, historical records keep the name the action happened under.

This means **you only need the accessor / column to be right at the moment of editing**. Stale rows from before the accessor was wired up will still show the old fallback value · they don't refresh.

If you want to backfill them:

```bash
php artisan tinker
> \LoggedCloud\PageStudio\Models\Activity::query()
>     ->whereNotNull('author_id')
>     ->chunkById(500, function ($rows) {
>         foreach ($rows as $r) {
>             $name = optional(\App\Models\User::find($r->author_id))->name;
>             if ($name) $r->update(['author_name' => $name]);
>         }
>     });
```

Same shape for `BlockLock`, `BlockComment`, `Presence` if you want full historical alignment.

## What if I want something other than name?

Some teams want emoji prefixes ("🟢 Alice"), role suffixes ("Alice · Editor"), or pure handles ("@alice"). The accessor pattern composes everything:

```php
protected function name(): Attribute
{
    return Attribute::get(function () {
        $base = $this->display_name ?? $this->email;
        return $this->is_admin ? $base.' · admin' : $base;
    });
}
```

The presence chip will compute initials from the resulting string, so "Alice · admin" becomes the chip "A" with the full label on hover. If your composed name doesn't initial-cleanly, consider returning the plain display name from the accessor and rendering decoration in the host app's own templates.

## Anonymous users

When `auth()->user()` returns null (anonymous browsing of a public-write demo) the package writes `'Anonymous'` as the author name. No way to customise this short of forking — but a guest-friendly app should gate the editor behind auth anyway, see [auth-gated-authoring.md](auth-gated-authoring.md).

---

## Related tutorials

- [Auth-gated authoring](auth-gated-authoring.md) · route + Livewire-component gating, role policies
- [Deploying to production](deploying-to-prod.md) · view-cache, render-cache, queue, migration safety
