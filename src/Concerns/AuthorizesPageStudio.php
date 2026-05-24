<?php

namespace LoggedCloud\PageStudio\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Gates Livewire mounts behind whatever gate the host app named in
 * `config('page-studio.gate')`. When the config key is null (default) the
 * trait is a no-op, so single-user apps work without extra wiring.
 */
trait AuthorizesPageStudio
{
    protected function authorizePageStudio(): void
    {
        $gate = config('page-studio.gate');
        if ($gate === null) return;

        if (! Gate::allows($gate)) {
            throw new AuthorizationException(
                "Page Studio access denied by the `$gate` gate.",
            );
        }
    }
}
