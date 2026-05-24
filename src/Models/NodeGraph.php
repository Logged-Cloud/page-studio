<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;

class NodeGraph extends Model
{
    protected $guarded = [];

    protected $casts = [
        'nodes' => 'array',
        'edges' => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'node_graphs';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteDefinition::class, 'route_id');
    }

    /**
     * Evaluate this graph against the supplied base context (typically route
     * variable values) and merge in every output-node value · returns the
     * context the page renderer should use.
     */
    public function evaluate(array $baseContext): array
    {
        return NodeGraphEngine::evaluate(
            (array) $this->nodes,
            (array) $this->edges,
            $baseContext,
        );
    }
}
