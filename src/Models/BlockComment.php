<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per review-thread message attached to a block. Top-level
 * comments (parent_id null) anchor a thread; replies stitch back via
 * parent_id. Comments live per Page row · the block_id is the
 * tree-node id from BlockFactory::make, so a comment survives moves
 * and reorders that preserve the id.
 */
class BlockComment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'resolved'    => 'bool',
        'resolved_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'block_comments';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    /**
     * Scope to a specific block's thread on a given page.
     */
    public function scopeForBlock(Builder $q, int $pageId, string $blockId): Builder
    {
        return $q->where('page_id', $pageId)->where('block_id', $blockId);
    }

    /**
     * Scope to open (unresolved) comments · the default view for the UI's
     * indicator pip + thread panel.
     */
    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('resolved', false);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Best-effort author relation · resolved against whatever model the
     * host app has configured in `config('auth.providers.users.model')`.
     * Returns null when no user model is wired so tests + ephemeral
     * setups don't blow up.
     */
    public function author(): ?BelongsTo
    {
        $model = config('auth.providers.users.model');
        if (! $model || ! class_exists($model)) {
            return null;
        }
        return $this->belongsTo($model, 'author_id');
    }
}
