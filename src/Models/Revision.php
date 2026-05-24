<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Revision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'blocks' => 'array',
        'nodes'  => 'array',
        'edges'  => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'revisions';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteDefinition::class, 'route_id');
    }
}
