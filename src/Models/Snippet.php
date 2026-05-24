<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable block-tree snippet · authors save any block (and its
 * subtree of children when it's a layout block) under a name, then
 * drop a fresh copy from the palette any time. Useful for repeated
 * headers, footers, signature blocks, CTAs and the like.
 */
class Snippet extends Model
{
    protected $guarded = [];

    protected $casts = [
        'block'   => 'array',
        'preview' => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'snippets';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }
}
