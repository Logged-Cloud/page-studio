<?php

namespace LoggedCloud\PageStudio\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LoggedCloud\PageStudio\Models\RouteDefinition;

/**
 * Dispatched by the page-studio editor when a RouteDefinition is saved · host apps
 * can listen for cache invalidation, audit logging, downstream sync.
 */
class RouteSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public RouteDefinition $route,
        public mixed $user = null,
    ) {}
}
