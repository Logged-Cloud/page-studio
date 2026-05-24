<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-page audit log · who did what when. Append-only · the editor
 * writes one row at each interesting verb (saved, published, comment
 * added, lock acquired) and the Activity rail-tab reads the last 30
 * back in reverse chronological order.
 *
 * The `payload` JSON is open-ended · today comments stash the block
 * id there so the summary can read "Bob commented on Heading", but
 * any verb can ride along whatever context helps the UI.
 */
class Activity extends Model
{
    protected $guarded = [];

    protected $table_attr_override = null;

    protected $casts = [
        'payload' => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'activity';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function scopeForPage(Builder $q, ?int $pageId, ?int $routeId = null): Builder
    {
        return $q->where(function (Builder $w) use ($pageId, $routeId) {
            if ($pageId !== null)  $w->orWhere('page_id', $pageId);
            if ($routeId !== null) $w->orWhere('route_id', $routeId);
            if ($pageId === null && $routeId === null) {
                // No binding · refuse to match anything so ephemeral
                // editors never accidentally tail global activity.
                $w->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Most-recent rows first · the feed UI never needs more than a
     * page or so. Default 30 matches the activityFeed computed.
     */
    public function scopeRecent(Builder $q, int $n = 30): Builder
    {
        return $q->orderByDesc('created_at')->limit($n);
    }
}
