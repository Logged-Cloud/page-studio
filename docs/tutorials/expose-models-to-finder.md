# Expose models to the Model finder

The **Model finder** node lets authors pull a row out of one of your
Eloquent models by id (or another column) and pipe its fields into a
page as variables. Which models show up in the node's `MODEL FQCN`
dropdown, which columns the author can look up by, and which fields
are exposed as output sockets is all controlled by a single attribute
on the model.

This tutorial covers:

1. Marking a model as available with `#[ExposeToModelFinder]`.
2. Declaring per-model `findBy` and `searchable` columns.
3. Choosing exactly which fields become output sockets (and keeping
   sensitive columns like `password` out by default).
4. Rebuilding the discovery cache.

---

## 1. Mark a model as available

Add the attribute to any Eloquent model you want to expose:

```php
use Illuminate\Database\Eloquent\Model;
use LoggedCloud\PageStudio\Attributes\ExposeToModelFinder;

#[ExposeToModelFinder]
class Customer extends Model {}
```

That single line is enough. The model now appears in the `MODEL FQCN`
dropdown the next time the cache is rebuilt (see step 4).

Models **without** the attribute are never offered, so internal /
admin-only models can't leak into authoring surfaces.

## 2. Per-model `findBy` and `searchable` columns

The attribute carries three optional parameters that shape what the
authoring UI exposes:

```php
#[ExposeToModelFinder(
    label:      'Guest',
    findBy:     ['id', 'email', 'uuid'],
    searchable: ['name', 'email', 'phone'],
)]
class Customer extends Model {}
```

| Parameter   | What it does                                                                                       |
| ----------- | -------------------------------------------------------------------------------------------------- |
| `label`     | Display name in the FQCN dropdown · defaults to the class basename.                                |
| `findBy`    | Columns the author can look the row up by · becomes the `Find by column` dropdown on the node.    |
| `searchable`| Columns a future fuzzy-search surface can use · recorded now so the attribute is the only source. |

When the author picks `Customer` in the FQCN dropdown, the `Find by
column` field flips from free-text to a `<select>` populated from
`['id', 'email', 'uuid']`. They can't pick a column the model didn't
opt into.

## 3. Exposing fields as sockets

The Model finder node has an `Expose fields as outputs` checkbox.
Turning it on switches the single `model` output for one socket per
column — useful when you want to drop `customer.name` and
`customer.email` into different blocks without a separate transform.

Sensitive columns must never become sockets. Two layers protect
that:

### 3a. Explicit allowlist (preferred)

Pass `expose: [...]` on the attribute:

```php
#[ExposeToModelFinder(
    findBy:  ['id', 'email'],
    expose:  ['id', 'name', 'email', 'created_at'],
)]
class Customer extends Model {}
```

Only the listed columns become sockets. The runtime evaluation
respects the same list, so a manually-wired edge can't leak a hidden
column either.

### 3b. Laravel `$hidden` fallback

If you don't pass `expose`, the node falls back to the model's
existing Laravel `$hidden` list (set via the `$hidden` property or
Laravel's own `#[Hidden]` attribute). Sensitive columns there stay
out by default:

```php
#[ExposeToModelFinder]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable {}
```

The User model above exposes every column **except** `password` and
`remember_token` as sockets. No `expose` needed.

If you want even tighter control, declare `expose` explicitly · it
wins over `$hidden`.

## 4. Rebuild the discovery cache

The page-studio service provider reads a cache file at
`bootstrap/cache/page-studio-models.php` at boot. Without it, no
models appear in the dropdown.

Rebuild manually:

```bash
php artisan page-studio:discover-models
```

The package's `composer.json` ships a `post-autoload-dump` hook that
runs this for you, so `composer install`, `composer update`, and
`composer dump-autoload` all refresh the cache automatically. The
command prints the count of attributed models it found:

```
Cached 3 model(s) → /work/bootstrap/cache/page-studio-models.php
```

If you see `Cached 0 model(s)` after adding the attribute, either:

- You haven't run `composer dump-autoload` since editing the model
  (Laravel may be serving the pre-attribute reflection cache).
- The model lives outside `app/Models` · pass `--dir=` and
  `--namespace=` to point at the right tree.

---

## Behaviour summary

| Author action                                  | What happens                                                 |
| ---------------------------------------------- | ------------------------------------------------------------ |
| Drop a Model finder node, open settings        | `MODEL FQCN` is a `<select>` of attributed models           |
| Pick a model                                   | `Find by column` becomes a `<select>` from its `findBy` list |
| Toggle "Expose fields as outputs"              | One socket per column in the `expose` allowlist (or non-`$hidden` cols)|
| Wire a sensitive column manually               | Runtime evaluation skips it · `password` never reaches a block |
| Remove the attribute, dump-autoload            | Model disappears from the dropdown next request             |
