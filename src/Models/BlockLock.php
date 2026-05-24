<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A short-lived "I am editing this block right now" claim · the editor
 * holds one per selected block and renews it every ~10s. When another
 * user opens the same page, their editor sees the live lock and renders
 * a read-only ribbon over the affected block.
 *
 * Liveness is driven entirely by `expires_at` · no background sweeper
 * is required, stale rows simply stop matching scopeActive. Releases
 * and tab-closes delete the row; missed releases age out in 30s.
 */
class BlockLock extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'block_locks';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    /**
     * Rows whose expires_at is still in the future · everything else is
     * dead weight from missed releases and gets cleaned up opportunistically.
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('expires_at', '>', now());
    }
}
