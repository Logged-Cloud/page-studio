<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteSegment extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'route_segments';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(RouteDefinition::class, 'route_id');
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(Variable::class, 'variable_id');
    }
}
