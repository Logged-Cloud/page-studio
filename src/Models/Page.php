<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $guarded = [];

    protected $casts = [
        'blocks' => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'pages';
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
     * Flatten the route's variables into the [name => first-example] shape the
     * renderer uses to substitute `{{ var }}` tokens during preview.
     */
    public function previewContext(): array
    {
        $route = $this->route()->with('segments.variable')->first();
        if (! $route) return [];

        $ctx = [];
        foreach ($route->segments as $s) {
            if ($s->kind !== 'variable' || ! $s->variable) continue;
            $first = (array) $s->variable->examples;
            $ctx[$s->variable->name] = $first[0] ?? '';
        }
        return $ctx;
    }
}
