<?php

namespace LoggedCloud\PageStudio\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use LoggedCloud\PageStudio\Concerns\AuthorizesPageStudio;
use LoggedCloud\PageStudio\Events\RouteSaved;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\RouteSegment;
use LoggedCloud\PageStudio\Models\Variable;
use LoggedCloud\PageStudio\Support\PathParser;
use LoggedCloud\PageStudio\Support\RouteCompiler;
use LoggedCloud\PageStudio\Support\TypeExamples;

class RouteBuilder extends Component
{
    use AuthorizesPageStudio;
    public ?int $routeId = null;

    public string $name        = '';
    public string $description = '';

    /**
     * Page builder · every route is GET. Kept as a stored field so the
     * compiler + Laravel router both have an answer when asked, but
     * deliberately not exposed in the UI.
     */
    public string $method = 'GET';

    /**
     * The raw text the user types into the path input. Slashes split it
     * into segments; `{name}` placeholders auto-rebind to existing
     * variables in the library.
     */
    public string $rawPath = '';

    /**
     * Working segment list · the source of truth while editing. Each entry
     * is `{kind: 'literal'|'variable', value: string, variable_id: ?int}`.
     */
    public array $segments = [];

    public bool $showVariablePanel = false;
    public ?int $editingSegmentIndex = null;

    public array $newVariable = [
        'name'        => '',
        'label'       => '',
        'type'        => 'slug',
        'regex'       => '',
        'description' => '',
        'examples'    => ['', '', ''],
    ];

    public function mount(?int $routeId = null): void
    {
        $this->authorizePageStudio();
        if ($routeId) {
            $route = RouteDefinition::with('segments.variable')->findOrFail($routeId);
            $this->routeId     = $route->id;
            $this->name        = $route->name;
            $this->method      = $route->method;
            $this->description = $route->description ?? '';
            $this->segments    = $route->segments->map(fn (RouteSegment $s) => [
                'kind'        => $s->kind,
                'value'       => $s->kind === 'variable'
                    ? ($s->variable->name ?? 'unknown')
                    : (string) $s->literal_value,
                'variable_id' => $s->variable_id,
            ])->all();
        }
        $this->rawPath = $this->segmentsToInputPath();
    }

    public function updatedRawPath(): void
    {
        $this->applyRawPath($this->rawPath);
    }

    /**
     * Render the segment array into the form the input field expects: the
     * leading slash is omitted because the prefix span already renders one,
     * so an empty path stays an empty input instead of showing "/".
     */
    protected function segmentsToInputPath(): string
    {
        $full = $this->segmentsToPath();
        return ltrim($full, '/');
    }

    /**
     * Parse a raw URL string into the segment array. Matches any `{name}`
     * placeholders to existing variables in the library by name.
     */
    public function applyRawPath(string $raw): void
    {
        $parsed = PathParser::parse($raw);

        $varNames = array_column(
            array_filter($parsed, fn ($s) => $s['kind'] === 'variable'),
            'value'
        );
        $library = $varNames
            ? Variable::whereIn('name', $varNames)->pluck('id', 'name')->all()
            : [];

        $this->segments = array_map(fn ($s) => [
            'kind'        => $s['kind'],
            'value'       => $s['value'],
            'variable_id' => $s['kind'] === 'variable' ? ($library[$s['value']] ?? null) : null,
        ], $parsed);
    }

    public function openVariablePanelFor(int $segmentIndex): void
    {
        if (! isset($this->segments[$segmentIndex])) return;
        $this->editingSegmentIndex = $segmentIndex;

        $existing = $this->segments[$segmentIndex];
        $minExamples = (int) config('page-studio.min_examples_per_variable', 3);

        // If the segment is already a variable, pre-fill the form from the
        // library so "edit rules" round-trips cleanly.
        if ($existing['kind'] === 'variable' && $existing['variable_id'] ?? null) {
            $var = Variable::find($existing['variable_id']);
            if ($var) {
                $this->newVariable = [
                    'name'        => $var->name,
                    'label'       => $var->label ?? '',
                    'type'        => $var->type,
                    'regex'       => $var->regex ?? '',
                    'description' => $var->description ?? '',
                    'examples'    => array_pad((array) $var->examples, $minExamples, ''),
                ];
                $this->showVariablePanel = true;
                return;
            }
        }

        $type = $this->guessType($existing['value']);

        $this->newVariable = [
            'name'        => $existing['kind'] === 'variable'
                ? $existing['value']
                : $this->suggestVariableName($existing['value']),
            'label'       => '',
            'type'        => $type,
            'regex'       => '',
            'description' => '',
            // Pre-fill with type-stock examples so UUID / int / slug variables
            // open ready to save · the user types in their own only when the
            // canned set is wrong for their domain.
            'examples'    => $this->seedExamplesFor($type, $existing['value'], $minExamples),
        ];

        $this->showVariablePanel = true;
    }

    /**
     * Lifecycle hook · when the user flips the type dropdown, swap the
     * examples to the new type's canned set IF the examples are still
     * either blank or untouched stock from the prior type. Custom user
     * entries are kept.
     */
    public function updatedNewVariableType(string $newType): void
    {
        $current = array_values(array_filter(
            array_map('trim', (array) ($this->newVariable['examples'] ?? [])),
            fn ($v) => $v !== ''
        ));

        // Check if current examples are stock for *any* of the other types ·
        // if so they're safe to replace. Otherwise the user has typed real
        // values and we leave them alone.
        $isStock = $current === [] || $this->matchesAnyStockSet($current);

        if ($isStock) {
            $minExamples = (int) config('page-studio.min_examples_per_variable', 3);
            $this->newVariable['examples'] = $this->seedExamplesFor($newType, '', $minExamples);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function seedExamplesFor(string $type, string $hint, int $minExamples): array
    {
        $stock = TypeExamples::for($type);

        // For enum / custom we can't generate anything · keep the literal
        // hint as a first example so the field is at least populated.
        if (! $stock) {
            $base = $hint !== '' ? [$hint] : [];
            return array_pad($base, $minExamples, '');
        }

        return array_pad($stock, $minExamples, '');
    }

    protected function matchesAnyStockSet(array $candidate): bool
    {
        foreach (array_keys(config('page-studio.variable_types', [])) as $t) {
            $stock = TypeExamples::for($t);
            if ($stock && array_values($stock) === array_values($candidate)) {
                return true;
            }
        }
        return false;
    }

    protected function guessType(string $literal): string
    {
        $literal = trim($literal);
        if ($literal === '') return 'slug';
        if (preg_match('/^\d+$/', $literal)) return 'int';
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $literal)) return 'uuid';
        if (preg_match('/^[A-Za-z]+$/', $literal)) return 'alpha';
        return 'slug';
    }

    public function closeVariablePanel(): void
    {
        $this->showVariablePanel = false;
        $this->editingSegmentIndex = null;
    }

    public function commitVariable(): void
    {
        $minExamples = (int) config('page-studio.min_examples_per_variable', 3);

        $data = $this->newVariable;
        $data['examples'] = array_values(array_filter(
            array_map('trim', (array) $data['examples']),
            fn ($v) => $v !== ''
        ));

        $this->validate([
            'newVariable.name'        => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/'],
            'newVariable.type'        => ['required', 'string'],
            'newVariable.regex'       => ['nullable', 'string'],
            'newVariable.description' => ['nullable', 'string'],
        ]);

        if (count($data['examples']) < $minExamples) {
            $this->addError('newVariable.examples', "Please provide at least {$minExamples} example values.");
            return;
        }

        if ($data['type'] === 'custom' && trim((string) $data['regex']) === '') {
            $this->addError('newVariable.regex', 'Custom type requires a regex.');
            return;
        }

        // Build an unsaved instance so we can run the regex check before
        // committing · bad examples must NEVER reach the database.
        $candidate = new Variable([
            'type'     => $data['type'],
            'regex'    => $data['regex'] ?: null,
            'examples' => $data['examples'],
        ]);
        $bad = $this->collectExampleMismatches($candidate);
        if (! empty($bad)) {
            foreach ($bad as $i => $msg) {
                $this->addError("newVariable.examples.$i", $msg);
            }
            return;
        }

        $variable = Variable::updateOrCreate(
            ['name' => $data['name']],
            [
                'label'       => $data['label'] ?: null,
                'type'        => $data['type'],
                'regex'       => $data['regex'] ?: null,
                'description' => $data['description'] ?: null,
                'examples'    => $data['examples'],
            ],
        );

        if (isset($this->segments[$this->editingSegmentIndex])) {
            $this->segments[$this->editingSegmentIndex] = [
                'kind'        => 'variable',
                'value'       => $variable->name,
                'variable_id' => $variable->id,
            ];
            $this->rawPath = $this->segmentsToInputPath();
        }

        $this->closeVariablePanel();
    }

    /**
     * Return [index => error message] for every stored example that fails
     * to match the variable's effective `where` regex. Empty array when
     * everything is fine or the type has no regex (e.g. enum / any).
     */
    public function collectExampleMismatches(Variable $variable): array
    {
        $where = $variable->whereConstraint();
        if (! $where) return [];

        $pattern = '/^'.str_replace('/', '\/', $where).'$/';
        $bad = [];
        foreach ((array) $variable->examples as $i => $example) {
            if (! @preg_match($pattern, (string) $example)) {
                $bad[$i] = "Example \"$example\" does not match the variable's rule.";
            }
        }
        return $bad;
    }

    public function convertSegmentToLiteral(int $segmentIndex): void
    {
        if (! isset($this->segments[$segmentIndex])) return;
        $this->segments[$segmentIndex]['kind'] = 'literal';
        $this->segments[$segmentIndex]['variable_id'] = null;
        $this->rawPath = $this->segmentsToInputPath();
    }

    public function removeSegment(int $segmentIndex): void
    {
        unset($this->segments[$segmentIndex]);
        $this->segments = array_values($this->segments);
        $this->rawPath = $this->segmentsToInputPath();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9_.-]*$/'],
        ]);

        $template = $this->segmentsToPath();

        $route = RouteDefinition::updateOrCreate(
            ['id' => $this->routeId],
            [
                'name'          => $this->name,
                'method'        => 'GET',
                'path_template' => $template,
                'description'   => $this->description ?: null,
            ],
        );

        $route->segments()->delete();
        foreach ($this->segments as $position => $segment) {
            $route->segments()->create([
                'position'      => $position,
                'kind'          => $segment['kind'],
                'literal_value' => $segment['kind'] === 'literal' ? $segment['value'] : null,
                'variable_id'   => $segment['kind'] === 'variable' ? ($segment['variable_id'] ?? null) : null,
            ]);
        }

        $this->routeId = $route->id;
        $this->dispatch('page-studio:route:saved', routeId: $route->id);
        RouteSaved::dispatch($route, auth()->user());
    }

    #[Computed]
    public function pathTemplate(): string
    {
        return $this->segmentsToPath();
    }

    #[Computed]
    public function compiled(): array
    {
        if (! $this->routeId) return [];
        $route = RouteDefinition::with('segments.variable')->find($this->routeId);
        return $route ? RouteCompiler::compile($route) : [];
    }

    protected function segmentsToPath(): string
    {
        return PathParser::compose(array_map(fn ($s) => [
            'kind'  => $s['kind'],
            'value' => $s['value'],
        ], $this->segments));
    }

    protected function suggestVariableName(string $literal): string
    {
        $literal = trim($literal);
        if ($literal === '') return 'id';
        if (preg_match('/^\d+$/', $literal)) return 'id';
        if (preg_match('/^[0-9a-fA-F-]{32,36}$/', $literal)) return 'uuid';
        $slug = preg_replace('/[^A-Za-z0-9]+/', '', $literal);
        return lcfirst($slug ?: 'value');
    }

    public function render()
    {
        return view('page-studio::livewire.route-builder', [
            'variableTypes' => config('page-studio.variable_types', []),
            'libraryVariables' => Variable::orderBy('name')->get(),
        ]);
    }
}
