<?php

namespace LoggedCloud\PageStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Variable extends Model
{
    protected $guarded = [];

    protected $casts = [
        'examples' => 'array',
    ];

    public function getTable(): string
    {
        return config('page-studio.table_prefix', 'page_studio_').'variables';
    }

    public function getConnectionName(): ?string
    {
        return config('page-studio.connection') ?? parent::getConnectionName();
    }

    public function segments(): HasMany
    {
        return $this->hasMany(RouteSegment::class, 'variable_id');
    }

    /**
     * Effective `where()` regex for this variable. For `enum`, build from the
     * stored examples; for `custom`, use the user-supplied regex; otherwise
     * pull from the config's built-in type table.
     */
    public function whereConstraint(): ?string
    {
        $types = config('page-studio.variable_types', []);

        if ($this->type === 'enum') {
            $opts = collect($this->examples ?? [])
                ->filter()
                ->map(fn ($v) => preg_quote((string) $v, '/'))
                ->all();
            return $opts ? '('.implode('|', $opts).')' : null;
        }

        if ($this->type === 'custom') {
            return $this->regex ?: null;
        }

        return $types[$this->type]['where'] ?? null;
    }

    /**
     * Laravel validation rule string · enables example-checking + form-side
     * validation when the route is hit.
     */
    public function validationRule(): ?string
    {
        $types = config('page-studio.variable_types', []);

        if ($this->type === 'enum') {
            return 'in:'.implode(',', (array) ($this->examples ?? []));
        }

        if ($this->type === 'custom' && $this->regex) {
            return 'regex:/^'.$this->regex.'$/';
        }

        return $types[$this->type]['validate'] ?? null;
    }
}
