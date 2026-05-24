<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-tab "I am viewing this page" heartbeat row · upserted every
 * few seconds by the editor's Alpine root. The activePeers computed
 * filters by seen_at to surface only currently-open tabs.
 *
 * session_id is the Livewire component instance id (the same handle
 * `$this->getId()` returns) so each browser tab gets its own row even
 * when the same user has the page open twice.
 */
class Presence extends Model
{
    protected $guarded = [];

    protected $casts = [
        'seen_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'presence';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    /**
     * Rows whose seen_at is within the last $ttl seconds · default 60s
     * gives a comfortable margin over the 8s heartbeat without keeping
     * orphaned tabs alive forever.
     */
    public function scopeActive(Builder $q, int $ttl = 60): Builder
    {
        return $q->where('seen_at', '>=', now()->subSeconds($ttl));
    }
}
