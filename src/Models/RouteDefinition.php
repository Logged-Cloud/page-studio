<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteDefinition extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'routes';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function segments(): HasMany
    {
        return $this->hasMany(RouteSegment::class, 'route_id')->orderBy('position');
    }

    /**
     * Rebuild path_template from the ordered segments. Call after segment
     * mutation so the cached template stays in sync.
     */
    public function refreshPathTemplate(): string
    {
        $parts = $this->segments()->with('variable')->get()->map(function (RouteSegment $s) {
            return $s->kind === 'variable'
                ? '{'.($s->variable->name ?? 'unknown').'}'
                : (string) $s->literal_value;
        });

        $this->path_template = '/'.$parts->implode('/');
        $this->save();

        return $this->path_template;
    }

    /**
     * Flatten the where() constraints for every variable used in this route.
     * Suitable for `Route::get(...)->where($route->whereConstraints())`.
     */
    public function whereConstraints(): array
    {
        return $this->segments()
            ->with('variable')
            ->where('kind', 'variable')
            ->get()
            ->mapWithKeys(fn (RouteSegment $s) => $s->variable
                ? [$s->variable->name => $s->variable->whereConstraint()]
                : [])
            ->filter()
            ->all();
    }
}
