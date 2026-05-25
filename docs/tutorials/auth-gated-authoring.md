# Auth-gated authoring

By default the page-builder is mounted wherever you put it · any visitor who can hit the URL can author. For most production apps you want only signed-in editors authoring, and possibly a subset of those (admins vs. regular users).

There are two layers to wire: route-level gating (who can reach the page) and component-level gating (what the page-builder allows once mounted).

## Layer 1 · Route-level gate

The simplest gate: stick the page-builder route behind Laravel's `auth` middleware.

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/admin/pages/{id}/edit', function (int $id) {
        return view('admin.page-edit', ['pageId' => $id]);
    });
});
```

Guests get bounced to your login flow before they see the editor at all. Good enough for solo / single-team apps.

## Layer 2 · Component-level gate

For finer control · "Alice can author marketing pages, Bob can only comment" · the package reads a Gate name from `config/page-studio.php`:

```php
// config/page-studio.php
'gate' => 'page-studio.manage',
```

Then define the gate in your `AppServiceProvider` (or wherever you keep policies):

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('page-studio.manage', function ($user) {
        return $user->is_admin || $user->role === 'editor';
    });
}
```

When the gate is set, **every Livewire component** in the package calls `Gate::allows($gateName)` on mount. A denial throws a 403 before the editor renders. This includes:

- `page-studio.route-builder`
- `page-studio.variable-library`
- `page-studio.page-builder`
- `page-studio.custom-node-form`

The collaborate-only path (block comments, presence) goes through the same gate · if you want a "viewer" role that can comment but not edit, you'd need to split the gate into two (e.g. `page-studio.view` + `page-studio.manage`) and wrap the relevant Livewire actions in your own policy checks.

## Pattern · role-based feature toggles

Set the gate to allow any signed-in user, then layer per-feature gates in your host app:

```php
// AppServiceProvider
Gate::define('page-studio.manage',   fn ($u) => $u !== null);   // any signed-in
Gate::define('page-studio.publish',  fn ($u) => $u->role === 'editor');
Gate::define('page-studio.delete',   fn ($u) => $u->is_admin);
```

Then in your custom blocks / nodes, gate the destructive actions:

```php
public function evaluate(array $inputs, array $settings, array $context): array
{
    if (! Gate::allows('page-studio.publish')) {
        return ['value' => '(preview only · publish requires editor role)'];
    }
    // ... real work
}
```

## Pattern · OAuth / SSO

A host app that uses an SSO provider (the logged.cloud family stack, Laravel Passport, Okta, etc.) sees authenticated users via the standard `auth()` facade. No special hookup · the gate above just works once a user is signed in.

The `source.auth_user` node automatically pulls the current `auth()->user()`. With `expose_fields` flipped on, every column of the configured `auth.providers.users.model` becomes a graph output, ready to substitute into the page via `{{ name }}`, `{{ email }}`, etc.

## Pattern · author attribution

If you want the activity feed (Save / Publish / Comment) to record the right user, the package automatically reads `auth()->user()->getAuthIdentifier()` and `name`. Anonymous saves (no logged-in user) get attributed to `"Someone"`. If your User model uses a non-standard name field, override the `currentAuthor()` method or just keep your model's `name` accessor populated.

## What's NOT in the gate

- The public render path (whatever route serves the rendered page to end users) does not consult the gate. It only matters for authoring. If you want to gate the rendered output (e.g. paid-content paywalls), wrap your render route in your own middleware.
- The render cache, if enabled, doesn't know about the gate. Cached HTML is reused regardless of who the requester is. Don't cache pages that personalise to the signed-in user · turn the cache off for those, or include the user id in the context so the cache key differs per user.

## Testing the gate

```php
it('refuses guests', function () {
    Gate::define('page-studio.manage', fn () => false);
    $this->get('/admin/pages/1/edit')->assertForbidden();
});

it('admits admins', function () {
    Gate::define('page-studio.manage', fn ($u) => $u->is_admin);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/pages/1/edit')->assertOk();
});
```
