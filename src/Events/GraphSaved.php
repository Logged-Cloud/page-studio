<?php

namespace LoggedCloud\PageStudio\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LoggedCloud\PageStudio\Models\NodeGraph;

/**
 * Dispatched by the page-studio editor when a NodeGraph is saved · host apps
 * can listen for cache invalidation, audit logging, downstream sync.
 */
class GraphSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public NodeGraph $graph,
        public mixed $user = null,
    ) {}
}
