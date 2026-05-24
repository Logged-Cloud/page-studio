<?php

namespace LoggedCloud\PageStudio\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use LoggedCloud\PageStudio\Concerns\AuthorizesPageStudio;
use LoggedCloud\PageStudio\Events\PageSaved;
use LoggedCloud\PageStudio\Events\GraphSaved;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Models\NodeGraph;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Support\BlockTree;
use LoggedCloud\PageStudio\Support\NodeGraphEngine;
use LoggedCloud\PageStudio\Support\PageRenderer;

class PageBuilder extends Component
{
    use AuthorizesPageStudio, WithFileUploads;

    /**
     * Pending file picked by an `image.upload` node's file input. The
     * paired `$uploadTargetProp` holds the wire-model path to write the
     * resulting URL to once it's stored.
     */
    public $uploadFile = null;
    public string $uploadTargetProp = '';

    /**
     * Either / or:
     *   - $routeId binds to a RouteDefinition · variables come from segments,
     *     page persists per-route (legacy default)
     *   - $pageId binds straight to a Page row · route is optional
     *   - both null = ephemeral · authored in-memory, no DB writes
     */
    public ?int $routeId = null;
    public ?int $pageId = null;

    /**
     * Caller-supplied variables, format:
     *   ['campaign_name' => 'Summer 2026', 'client_email' => 'foo@bar.com']
     * OR the richer form:
     *   [['name' => 'campaign_name', 'label' => 'Campaign', 'preview' => 'Summer']]
     * Merged with any route-derived variables · keys collide → caller wins.
     */
    public array $customVariables = [];

    /**
     * Email mode · when true the palette hides blocks whose
     * `emailSafe(): false` (CSS grid layouts, color-mix backgrounds,
     * anything that doesn't survive Outlook + Gmail). Existing saved
     * blocks of those types still evaluate and render · the gate is
     * UI-only.
     */
    public bool $emailMode = false;

    /**
     * Per-page metadata · today the email use case writes subject /
     * preheader / reply-to here, but the JSON column is open-ended so
     * SEO meta, scheduled-publish flags, locale codes etc can pile on
     * later without another migration.
     *
     * @var array<string, mixed>
     */
    public array $meta = ['subject' => '', 'preheader' => '', 'replyTo' => ''];

    /**
     * Authored block tree · the root list. Layout blocks carry `children`
     * keyed by slot name; each slot is a list of nested blocks. Persisted
     * verbatim as JSON on the `pages.blocks` column.
     */
    public array $blocks = [];

    /**
     * Path to the currently-selected block · empty string = nothing selected.
     * See BlockTree for the path format.
     */
    public string $selectedPath = '';

    public bool $previewMode = false;

    /**
     * Last successful save timestamp · shown in the top bar so the user sees
     * their work persisted. Null until they have saved at least once on this
     * page load.
     */
    public ?string $lastSavedAt = null;

    /**
     * Lifecycle state · 'draft' or 'published'. Drafts are hidden from the
     * auto-registered Studio routes; published pages render normally. A
     * non-null `publishAt` in the future keeps the page invisible until
     * then (treated as a "scheduled" sub-state in the UI).
     */
    public string $status = 'draft';
    public ?string $publishAt = null;
    public ?string $publishedAt = null;

    /**
     * Node-graph state · the variable-composition layer that lives in the
     * bottom drawer. Persisted in its own table (page_studio_node_graphs).
     *
     * @var array<int, array{id:string,type:string,position:array,settings:array}>
     */
    public array $nodes = [];
    /** @var array<int, array{id:string,from_node:string,from_socket:string,to_node:string,to_socket:string}> */
    public array $edges = [];

    public ?string $selectedNodeId = null;
    /**
     * Default-open so the node editor is visible on first visit · the user
     * can toggle it shut with the Nodes button if they want canvas-only mode.
     */
    public bool $drawerOpen = true;
    /**
     * Pending half-built connection · when the user clicks an output socket
     * we remember it; the next click on a compatible input socket commits
     * the edge. ['node' => id, 'socket' => key] or null.
     *
     * @var array{node:string,socket:string}|null
     */
    public ?array $pendingConnection = null;

    /**
     * Undo / redo stacks of `[nodes, edges]` snapshots · capped to keep the
     * Livewire snapshot payload from ballooning over a long edit session.
     *
     * @var array<int, array{nodes: array, edges: array}>
     */
    public array $undoStack = [];
    public array $redoStack = [];

    protected const HISTORY_LIMIT = 40;

    /**
     * Save the current node/edge state before a mutation · drops the redo
     * stack since a new branch invalidates redoable steps.
     */
    protected function pushHistory(): void
    {
        $this->undoStack[] = ['blocks' => $this->blocks, 'nodes' => $this->nodes, 'edges' => $this->edges];
        if (count($this->undoStack) > self::HISTORY_LIMIT) array_shift($this->undoStack);
        $this->redoStack = [];
    }

    public function undo(): void
    {
        if (! $this->undoStack) return;
        $this->redoStack[] = ['blocks' => $this->blocks, 'nodes' => $this->nodes, 'edges' => $this->edges];
        if (count($this->redoStack) > self::HISTORY_LIMIT) array_shift($this->redoStack);
        $snap = array_pop($this->undoStack);
        $this->blocks = $snap['blocks'] ?? $this->blocks;
        $this->nodes  = $snap['nodes']  ?? $this->nodes;
        $this->edges  = $snap['edges']  ?? $this->edges;
    }

    public function redo(): void
    {
        if (! $this->redoStack) return;
        $this->undoStack[] = ['blocks' => $this->blocks, 'nodes' => $this->nodes, 'edges' => $this->edges];
        if (count($this->undoStack) > self::HISTORY_LIMIT) array_shift($this->undoStack);
        $snap = array_pop($this->redoStack);
        $this->blocks = $snap['blocks'] ?? $this->blocks;
        $this->nodes  = $snap['nodes']  ?? $this->nodes;
        $this->edges  = $snap['edges']  ?? $this->edges;
    }

    public function mount(?int $routeId = null, ?int $pageId = null, array $variables = [], bool $emailMode = false): void
    {
        $this->authorizePageStudio();
        $this->routeId = $routeId;
        $this->pageId  = $pageId;
        $this->emailMode = $emailMode;
        $this->customVariables = $this->normaliseVariables($variables);

        // Bind to a Page row by pageId first, then fall back to the legacy
        // route_id lookup. Either path may return null · in that case the
        // editor runs ephemerally (no DB writes on save).
        $page = match (true) {
            $pageId !== null  => Page::find($pageId),
            $routeId !== null => Page::where('route_id', $routeId)->first(),
            default           => null,
        };
        $this->blocks = $page ? BlockTree::sanitise((array) $page->blocks) : [];
        $this->meta   = array_merge(
            ['subject' => '', 'preheader' => '', 'replyTo' => ''],
            $page ? (array) ($page->meta ?? []) : [],
        );
        $this->lastSavedAt = $page?->updated_at?->toIso8601String();
        $this->status      = (string) ($page?->status ?? 'draft');
        // HTML datetime-local inputs want `YYYY-MM-DDTHH:MM` · the trailing
        // seconds confuse some browsers, so trim to minute precision.
        $this->publishAt   = $page?->publish_at?->format('Y-m-d\TH:i');
        $this->publishedAt = $page?->published_at?->toIso8601String();

        $graph = $routeId !== null ? NodeGraph::where('route_id', $routeId)->first() : null;
        $this->nodes = $graph ? (array) $graph->nodes : [];
        $this->edges = $graph ? (array) $graph->edges : [];

        // Honour the kill-switch · saved graphs still evaluate (so existing
        // output variables keep flowing into pages) but the drawer never
        // opens and any new add* methods refuse.
        if (! $this->nodeEditorEnabled()) {
            $this->drawerOpen = false;
        }
    }

    /**
     * Accept either the flat name => value shape or a list of full
     * definitions and normalise to the richer list form the rest of the
     * component consumes.
     *
     * @return array<int, array{name: string, label: string, preview: string}>
     */
    protected function normaliseVariables(array $input): array
    {
        $out = [];
        foreach ($input as $key => $value) {
            if (is_int($key) && is_array($value) && isset($value['name'])) {
                // Already-shaped entry · keep verbatim.
                $out[] = [
                    'name'    => (string) $value['name'],
                    'label'   => (string) ($value['label']   ?? $value['name']),
                    'preview' => (string) ($value['preview'] ?? ''),
                ];
                continue;
            }

            $name = (string) $key;
            if ($name === '') continue;

            // Eloquent model · expose a root entry plus a dot-path entry per
            // attribute. The renderer's substitute() walks data_get, so the
            // author can type `{{ user.email }}` and get the leaf value.
            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                $out[] = $this->variableEntry($name, $name, $this->modelPreview($value));
                foreach ($value->attributesToArray() as $col => $v) {
                    if (is_scalar($v) || $v === null) {
                        $out[] = $this->variableEntry(
                            "$name.$col",
                            $col,
                            $this->scalarPreview($v),
                        );
                    }
                }
                continue;
            }

            if ($value instanceof \Illuminate\Support\Collection) {
                $out[] = $this->variableEntry($name, $name, 'collection ('.$value->count().')');
                continue;
            }

            if (is_array($value)) {
                $out[] = $this->variableEntry($name, $name, 'array ('.count($value).')');
                foreach ($value as $k => $v) {
                    if (is_scalar($v) || $v === null) {
                        $out[] = $this->variableEntry(
                            "$name.$k",
                            (string) $k,
                            $this->scalarPreview($v),
                        );
                    }
                }
                continue;
            }

            $out[] = $this->variableEntry($name, $name, $this->scalarPreview($value));
        }
        return $out;
    }

    protected function variableEntry(string $name, string $label, string $preview): array
    {
        return ['name' => $name, 'label' => $label, 'preview' => $preview];
    }

    /**
     * Best-effort human label for an Eloquent model shown next to the var
     * chip · falls back to `Class #id` when no name-ish column is set.
     */
    protected function modelPreview(\Illuminate\Database\Eloquent\Model $m): string
    {
        // Eloquent's base __toString returns the JSON dump · skip past it
        // and look for a sensible human label first.
        foreach (['name', 'label', 'title', 'email'] as $candidate) {
            $v = $m->getAttribute($candidate);
            if (is_scalar($v) && $v !== '') return (string) $v;
        }
        return class_basename($m).' #'.($m->getKey() ?? '?');
    }

    protected function scalarPreview(mixed $v): string
    {
        if ($v === null)  return '';
        if (is_bool($v))  return $v ? 'true' : 'false';
        return (string) $v;
    }

    /**
     * Insert a new block of the given type into the (parentPath, slot, index)
     * target. parentPath='' + slot=null = root.
     */
    public function addBlock(string $type, string $parentPath = '', ?string $slot = null, ?int $index = null): void
    {
        // Email mode · refuse blocks the BlockType marks as not email-safe
        // so wire-callers can't sidestep the palette filter.
        if ($this->emailMode) {
            $schema = config("page-studio.blocks.$type");
            if (is_array($schema) && ($schema['email_safe'] ?? true) === false) return;
        }

        $block = BlockFactory::make($type);
        if (! $block) return;

        $this->pushHistory();
        $target = $index ?? count($this->slotContents($parentPath, $slot));
        $this->blocks = BlockTree::insert($this->blocks, $parentPath, $slot, $target, $block);

        // Auto-select the new block · path = parentPath + slot + index.
        $this->selectedPath = $this->pathFor($parentPath, $slot, $target);
    }

    public function selectBlock(string $path): void
    {
        $this->selectedPath = BlockTree::get($this->blocks, $path) ? $path : '';
    }

    public function clearSelection(): void
    {
        $this->selectedPath = '';
    }

    public function removeBlock(string $path): void
    {
        $this->pushHistory();
        $this->blocks = BlockTree::remove($this->blocks, $path);
        if (str_starts_with($this->selectedPath, $path)) {
            $this->selectedPath = '';
        }
    }

    /**
     * Duplicate the block at $path · inserts a deep clone (with a fresh id
     * tree) at index + 1 inside the same parent + slot. Pushes a history
     * snapshot first so undo restores the pre-duplication tree.
     */
    public function duplicateBlock(string $path): void
    {
        $original = BlockTree::get($this->blocks, $path);
        if (! $original) return;

        $this->pushHistory();

        [$parentPath, $slot, $index] = $this->splitLastPath($path);
        $clone = $this->cloneBlockWithFreshIds($original);
        $this->blocks = BlockTree::insert($this->blocks, $parentPath, $slot, $index + 1, $clone);
        $this->selectedPath = $this->pathFor($parentPath, $slot, $index + 1);
    }

    // ─── Snippet library ────────────────────────────────────────────────

    /**
     * Save the block at $path as a named snippet · deep-clones the subtree
     * with fresh ids so future drops never collide with the source block.
     * Silently returns when the path does not resolve, when the name is
     * empty, or when a snippet by that name already exists (a rename via
     * the manager is required to overwrite).
     */
    public function saveAsSnippet(string $path, string $name, string $label = ''): void
    {
        $name = trim($name);
        if ($name === '') return;

        $source = BlockTree::get($this->blocks, $path);
        if (! $source) return;

        // Best-effort icon · use the BlockType's icon if the registry
        // declares one, falling back to the default star.
        $schema = config('page-studio.blocks.'.($source['type'] ?? ''), []);
        $icon   = (string) ($schema['icon'] ?? '★');
        // Trim to the 8-char column width · multi-byte safe.
        if (mb_strlen($icon) > 8) $icon = mb_substr($icon, 0, 8);

        $clone = $this->cloneBlockWithFreshIds($source);

        $snippet = \LoggedCloud\PageStudio\Models\Snippet::create([
            'name'      => $name,
            'label'     => $label !== '' ? $label : $name,
            'icon'      => $icon !== '' ? $icon : '★',
            'group'     => 'snippets',
            'block'     => $clone,
            'author_id' => auth()->id(),
        ]);

        $this->dispatch('page-studio:snippet:saved', snippetId: $snippet->id);
    }

    /**
     * Drop a fresh copy of the saved snippet into the (parentPath, slot, index)
     * target · mirrors addBlock's call shape. Silently returns when the named
     * snippet doesn't exist so a stale palette button can't crash the editor.
     */
    public function dropSnippet(string $snippetName, string $parentPath = '', ?string $slot = null, ?int $index = null): void
    {
        $row = \LoggedCloud\PageStudio\Models\Snippet::where('name', $snippetName)->first();
        if (! $row) return;

        $tree = (array) $row->block;
        if (empty($tree) || ! isset($tree['type'])) return;

        $this->pushHistory();
        // Re-id at drop time so two drops of the same snippet land as
        // distinct subtrees · wire:key collisions otherwise wreck the
        // editor's morphdom diff.
        $clone  = $this->cloneBlockWithFreshIds($tree);
        $target = $index ?? count($this->slotContents($parentPath, $slot));
        $this->blocks = BlockTree::insert($this->blocks, $parentPath, $slot, $target, $clone);
        $this->selectedPath = $this->pathFor($parentPath, $slot, $target);
    }

    /**
     * Lightweight snippet directory · drops the block tree from the
     * snapshot so the Livewire payload stays tiny. The full subtree only
     * comes back across the wire on drop, when dropSnippet loads the row.
     *
     * @return array<int, array{id:int,name:string,label:string,icon:string,group:string}>
     */
    #[Computed]
    public function snippetLibrary(): array
    {
        return \LoggedCloud\PageStudio\Models\Snippet::orderBy('group')
            ->orderBy('label')
            ->get(['id', 'name', 'label', 'icon', 'group'])
            ->map(fn ($s) => [
                'id'    => (int) $s->id,
                'name'  => (string) $s->name,
                'label' => (string) ($s->label ?: $s->name),
                'icon'  => (string) ($s->icon ?: '★'),
                'group' => (string) ($s->group ?: 'snippets'),
            ])
            ->all();
    }

    public function renameSnippet(int $id, string $newName, string $newLabel = ''): void
    {
        $newName = trim($newName);
        if ($newName === '') return;
        $row = \LoggedCloud\PageStudio\Models\Snippet::find($id);
        if (! $row) return;
        $row->name  = $newName;
        $row->label = $newLabel !== '' ? $newLabel : $newName;
        $row->save();
    }

    public function deleteSnippet(int $id): void
    {
        $row = \LoggedCloud\PageStudio\Models\Snippet::find($id);
        if (! $row) return;
        $row->delete();
    }

    // ─── Search-and-replace ─────────────────────────────────────────────

    /**
     * Walk every block in the tree and apply $find -> $replace across each
     * string-valued setting. Returns the count of blocks where at least one
     * setting changed (the UI surfaces this as "N matches replaced").
     *
     * In regex mode bad patterns are swallowed (the @ on preg_replace plus a
     * null-result bailout) so an in-progress pattern never blows up the editor.
     */
    public function searchAndReplace(string $find, string $replace, bool $regex = false): int
    {
        if ($find === '') return 0;

        $changedBlocks = 0;
        $apply = function (array $block) use ($find, $replace, $regex, &$apply, &$changedBlocks): array {
            $touched = false;
            foreach ($block['settings'] ?? [] as $key => $value) {
                if (! is_string($value)) continue;
                if ($regex) {
                    $next = @preg_replace($find, $replace, $value);
                    if ($next === null) return $block;
                } else {
                    $next = str_replace($find, $replace, $value);
                }
                if ($next !== $value) {
                    $block['settings'][$key] = $next;
                    $touched = true;
                }
            }
            if ($touched) $changedBlocks++;

            if (! empty($block['children']) && is_array($block['children'])) {
                foreach ($block['children'] as $slot => $kids) {
                    if (! is_array($kids)) continue;
                    foreach ($kids as $i => $kid) {
                        $block['children'][$slot][$i] = $apply($kid);
                    }
                }
            }
            return $block;
        };

        // Pre-flight bad-regex check · if even one block's apply bails out
        // because the pattern is invalid, we want zero mutations on the tree.
        if ($regex) {
            $probe = @preg_replace($find, $replace, '');
            if ($probe === null) return 0;
        }

        $this->pushHistory();
        $next = [];
        foreach ($this->blocks as $b) {
            $next[] = $apply($b);
        }
        $this->blocks = $next;

        // If nothing changed, pop the history snapshot we just pushed so a
        // no-op replace doesn't burn an undo step.
        if ($changedBlocks === 0) {
            array_pop($this->undoStack);
        }

        return $changedBlocks;
    }

    /**
     * Deep clone a block subtree assigning every block (and every nested
     * child) a fresh id · prevents wire:key collisions in the editor.
     */
    protected function cloneBlockWithFreshIds(array $block): array
    {
        $block['id'] = bin2hex(random_bytes(6));
        if (! empty($block['children']) && is_array($block['children'])) {
            foreach ($block['children'] as $slot => $kids) {
                if (! is_array($kids)) continue;
                foreach ($kids as $i => $kid) {
                    $block['children'][$slot][$i] = $this->cloneBlockWithFreshIds($kid);
                }
            }
        }
        return $block;
    }

    /**
     * Same split-last-segment helper BlockTree uses internally · the
     * protected version isn't reachable from here so we re-derive it.
     */
    protected function splitLastPath(string $path): array
    {
        $bits = array_values(array_filter(explode('/', $path), fn ($b) => $b !== ''));
        $lastIndex = (int) array_pop($bits);
        $lastSlot  = $bits ? array_pop($bits) : null;
        $parent    = implode('/', $bits);
        return [$parent, $lastSlot, $lastIndex];
    }

    /**
     * Move a block · either reorder within its parent or change parent + slot.
     */
    public function moveBlock(string $fromPath, string $toParentPath = '', ?string $toSlot = null, ?int $toIndex = null): void
    {
        $this->pushHistory();
        $target = $toIndex ?? count($this->slotContents($toParentPath, $toSlot));
        $this->blocks = BlockTree::move($this->blocks, $fromPath, $toParentPath, $toSlot, $target);
        $this->selectedPath = $this->pathFor($toParentPath, $toSlot, $target);
    }

    /**
     * Sibling-axis nudges · for the keyboard-driven up / down arrows on the
     * block handle. parentPath is the parent (root if top-level), slot is
     * the slot name (null for root), index is the block's current index.
     */
    public function moveSibling(string $parentPath, ?string $slot, int $index, int $delta): void
    {
        $fromPath = $this->pathFor($parentPath, $slot, $index);
        $kids = $this->slotContents($parentPath, $slot);
        $target = max(0, min(count($kids) - 1, $index + $delta));
        if ($target === $index) return;
        // moveBlock uses an index in the "after removal" coordinate space, so
        // bump the target by one when moving down so the block actually swaps.
        $this->moveBlock($fromPath, $parentPath, $slot, $delta > 0 ? $target + 1 : $target);
    }

    public function togglePreview(): void
    {
        $this->previewMode = ! $this->previewMode;
    }

    /**
     * Livewire hook · runs when the file input bound to `uploadFile` lands a
     * picked file. Stores it on the configured disk and writes the public
     * URL into whichever node setting the user targeted.
     */
    public function updatedUploadFile(): void
    {
        if (! $this->uploadFile) return;
        // Whitelist the target prop so this can only ever touch a node
        // setting · prevents anyone shaping the file form into a write to
        // an unrelated public property.
        if (! preg_match('/^nodes\.\d+\.settings\.[A-Za-z_][A-Za-z0-9_]*$/', $this->uploadTargetProp)) {
            $this->uploadFile = null;
            return;
        }

        $disk = config('page-studio.upload_disk', 'public');
        $path = $this->uploadFile->store('page-studio-uploads', $disk);
        $url  = \Illuminate\Support\Facades\Storage::disk($disk)->url($path);

        data_set($this, $this->uploadTargetProp, $url);
        $this->uploadFile = null;
        $this->uploadTargetProp = '';
    }

    public function clearUpload(string $prop): void
    {
        if (! preg_match('/^nodes\.\d+\.settings\.[A-Za-z_][A-Za-z0-9_]*$/', $prop)) return;
        data_set($this, $prop, '');
    }

    // ─── Node graph operations ──────────────────────────────────────────────

    public function toggleDrawer(): void
    {
        if (! $this->nodeEditorEnabled()) return;
        $this->drawerOpen = ! $this->drawerOpen;
    }

    public function addNode(string $type, ?int $x = null, ?int $y = null): void
    {
        if (! $this->nodeEditorEnabled()) return;
        if (in_array($type, (array) config('page-studio.disabled_nodes', []), true)) return;

        $this->pushHistory();
        // Node-type keys contain dots ('source.route_variable') · use the
        // map directly rather than config('page-studio.nodes.X.Y') which
        // would walk the dots as nested keys and miss.
        $library = config('page-studio.nodes', []);
        $schema = $library[$type] ?? null;
        if (! $schema) return;

        $settings = [];
        foreach ($schema['settings'] ?? [] as $key => $def) {
            $settings[$key] = $def['default'] ?? '';
        }

        $id = 'n_'.bin2hex(random_bytes(4));
        $this->nodes[] = [
            'id'       => $id,
            'type'     => $type,
            'position' => ['x' => $x ?? 100, 'y' => $y ?? 80],
            'settings' => $settings,
        ];
        $this->selectedNodeId = $id;
    }

    /**
     * Drop-target for variable chips · drags a chip onto the canvas, gets a
     * Route-variable source node with the chip's name pre-filled.
     */
    public function addNodeForVariable(string $variableName, ?int $x = null, ?int $y = null): void
    {
        if (! $this->nodeEditorEnabled()) return;
        if (in_array('source.route_variable', (array) config('page-studio.disabled_nodes', []), true)) return;

        $this->pushHistory();
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $variableName)) return;

        $library = config('page-studio.nodes', []);
        $schema = $library['source.route_variable'] ?? null;
        if (! $schema) return;

        $id = 'n_'.bin2hex(random_bytes(4));
        $this->nodes[] = [
            'id'       => $id,
            'type'     => 'source.route_variable',
            'position' => ['x' => $x ?? 100, 'y' => $y ?? 80],
            'settings' => ['variable_name' => $variableName],
        ];
        $this->selectedNodeId = $id;
    }

    public function selectNode(?string $id): void
    {
        $this->selectedNodeId = $id && $this->findNode($id) ? $id : null;
        // Clear pending connection · selecting elsewhere shouldn't strand a wire.
        $this->pendingConnection = null;
    }

    public function moveNode(string $id, int $x, int $y): void
    {
        foreach ($this->nodes as $i => $node) {
            if ($node['id'] === $id) {
                $this->nodes[$i]['position'] = ['x' => $x, 'y' => $y];
                return;
            }
        }
    }

    public function toggleMuted(string $id): void
    {
        $this->pushHistory();
        foreach ($this->nodes as $i => $node) {
            if ($node['id'] === $id) {
                $this->nodes[$i]['muted'] = ! ($node['muted'] ?? false);
                return;
            }
        }
    }

    public function duplicateNode(string $id): void
    {
        $this->pushHistory();
        foreach ($this->nodes as $node) {
            if ($node['id'] !== $id) continue;
            $clone = $node;
            $clone['id'] = 'n_'.bin2hex(random_bytes(4));
            $clone['position'] = [
                'x' => (int) ($node['position']['x'] ?? 0) + 30,
                'y' => (int) ($node['position']['y'] ?? 0) + 30,
            ];
            $this->nodes[] = $clone;
            $this->selectedNodeId = $clone['id'];
            return;
        }
    }

    /**
     * Paste a subgraph (nodes + edges between them) shifted by (dx,dy) so
     * the cluster lands where the user pressed Ctrl-V. Node ids are
     * rewritten and edges are remapped to the new ids in one history step.
     *
     * @param array<int, array> $nodes
     * @param array<int, array> $edges
     */
    public function pasteSubgraph(array $nodes, array $edges, int $dx, int $dy): void
    {
        if (! $nodes) return;
        $this->pushHistory();

        $idMap = [];
        $newIds = [];
        foreach ($nodes as $node) {
            $oldId = (string) ($node['id'] ?? '');
            if ($oldId === '') continue;
            $newId = 'n_'.bin2hex(random_bytes(4));
            $idMap[$oldId] = $newId;
            $node['id'] = $newId;
            $node['position'] = [
                'x' => max(0, (int) ($node['position']['x'] ?? 0) + $dx),
                'y' => max(0, (int) ($node['position']['y'] ?? 0) + $dy),
            ];
            $this->nodes[] = $node;
            $newIds[] = $newId;
        }
        foreach ($edges as $e) {
            if (! isset($idMap[$e['from_node'] ?? ''], $idMap[$e['to_node'] ?? ''])) continue;
            $this->edges[] = [
                'id'          => 'e_'.bin2hex(random_bytes(4)),
                'from_node'   => $idMap[$e['from_node']],
                'from_socket' => $e['from_socket'] ?? 'value',
                'to_node'     => $idMap[$e['to_node']],
                'to_socket'   => $e['to_socket'] ?? 'value',
            ];
        }
        $this->selectedNodeId = $newIds[0] ?? null;
    }

    /**
     * Remove every node in $ids and any edges touching them · one history
     * step, so the user can undo a multi-delete with a single Ctrl-Z.
     *
     * @param array<int, string> $ids
     */
    public function removeNodes(array $ids): void
    {
        if (! $ids) return;
        $this->pushHistory();
        $set = array_flip($ids);
        $this->nodes = array_values(array_filter($this->nodes, fn ($n) => ! isset($set[$n['id']])));
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => ! isset($set[$e['from_node']]) && ! isset($set[$e['to_node']]),
        ));
        if (isset($set[(string) $this->selectedNodeId])) $this->selectedNodeId = null;
    }

    public function removeNode(string $id): void
    {
        $this->pushHistory();
        $this->nodes = array_values(array_filter($this->nodes, fn ($n) => $n['id'] !== $id));
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => $e['from_node'] !== $id && $e['to_node'] !== $id,
        ));
        if ($this->selectedNodeId === $id) $this->selectedNodeId = null;
        if (($this->pendingConnection['node'] ?? null) === $id) $this->pendingConnection = null;
    }

    /**
     * Click-to-connect socket flow · clicking an output socket sets a pending
     * source; the next click on an input socket of a different node commits
     * the edge. Re-clicking the same socket cancels the pending state.
     */
    public function startConnection(string $node, string $socket): void
    {
        // Toggle off when re-clicking the same socket · easy way to cancel.
        if ($this->pendingConnection
            && $this->pendingConnection['node'] === $node
            && $this->pendingConnection['socket'] === $socket
        ) {
            $this->pendingConnection = null;
            return;
        }
        $this->pendingConnection = ['node' => $node, 'socket' => $socket];
    }

    public function completeConnection(string $toNode, string $toSocket): void
    {
        $this->pushHistory();
        $p = $this->pendingConnection;
        if (! $p || $p['node'] === $toNode) { $this->pendingConnection = null; return; }

        // Each input socket only accepts one wire · drop any prior edge to it.
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => ! ($e['to_node'] === $toNode && $e['to_socket'] === $toSocket),
        ));

        $this->edges[] = [
            'id'          => 'e_'.bin2hex(random_bytes(4)),
            'from_node'   => $p['node'],
            'from_socket' => $p['socket'],
            'to_node'     => $toNode,
            'to_socket'   => $toSocket,
        ];
        $this->pendingConnection = null;
    }

    /**
     * Store / clear a manual bend point on an edge in stage-local coords.
     * The renderer routes the wire through this point instead of the
     * auto-bezier when present · clear by passing nulls.
     */
    public function bendEdge(string $edgeId, ?int $x, ?int $y): void
    {
        foreach ($this->edges as $i => $e) {
            if (($e['id'] ?? '') !== $edgeId) continue;
            if ($x === null || $y === null) {
                unset($this->edges[$i]['bend']);
            } else {
                $this->edges[$i]['bend'] = ['x' => $x, 'y' => $y];
            }
            $this->edges = array_values($this->edges);
            return;
        }
    }

    public function disconnectInput(string $toNode, string $toSocket): void
    {
        $this->pushHistory();
        $this->edges = array_values(array_filter(
            $this->edges,
            fn ($e) => ! ($e['to_node'] === $toNode && $e['to_socket'] === $toSocket),
        ));
    }

    /**
     * Auto-layout the node graph in layered passes · sources at the left,
     * transforms in the middle (one column per BFS layer), output at the
     * right, notes parked in their own bottom row.
     */
    public function tidy(): void
    {
        $this->pushHistory();
        if (empty($this->nodes)) return;

        $W       = 220; // node x-stride (width + gap)
        $H       = 130; // node y-stride
        $START_X = 40;
        $START_Y = 40;

        // Adjacency · upstream[id] = [parent ids]
        $upstream = [];
        foreach ($this->nodes as $n) $upstream[$n['id']] = [];
        foreach ($this->edges as $e) {
            if (! isset($upstream[$e['to_node']])) continue;
            $upstream[$e['to_node']][] = $e['from_node'];
        }

        // Depth = longest path from any source. Cycles cap at 0.
        $depth = [];
        $compute = function (string $id, array $stack = []) use (&$compute, &$depth, $upstream): int {
            if (isset($depth[$id])) return $depth[$id];
            if (in_array($id, $stack, true)) return 0;
            $max = 0;
            foreach ($upstream[$id] as $p) {
                $max = max($max, 1 + $compute($p, [...$stack, $id]));
            }
            return $depth[$id] = $max;
        };
        foreach ($this->nodes as $n) $compute($n['id']);

        // Push output nodes to the rightmost column so they're always visible.
        $maxDepth = max($depth + [0]);
        $rightCol = $maxDepth + 1;

        // Group nodes by their column · notes parked at the bottom of column 0.
        $cols = [];
        foreach ($this->nodes as $n) {
            $id = $n['id'];
            $col = match ($n['type'] ?? '') {
                'output'                  => $rightCol,
                'note'                    => -1,
                default                   => $depth[$id],
            };
            $cols[$col][] = $id;
        }

        // Re-write each node's position. The cols array goes negative for
        // notes; offset every column when reading so we land in the visible
        // canvas without forcing the user to scroll left.
        $colKeys = array_keys($cols);
        sort($colKeys, SORT_NUMERIC);
        $offset = abs(min($colKeys));   // 0 when there are no notes

        $byId = [];
        foreach ($this->nodes as $i => $node) $byId[$node['id']] = $i;

        foreach ($cols as $col => $ids) {
            $x = $START_X + ($col + $offset) * $W;
            foreach (array_values($ids) as $row => $id) {
                $y = $START_Y + $row * $H;
                $this->nodes[$byId[$id]]['position'] = ['x' => $x, 'y' => $y];
            }
        }
    }

    public function saveGraph(): void
    {
        if ($this->routeId === null) {
            // No route bound · graphs are scoped per-route in the schema,
            // so we keep the wires in memory and bounce a saved event for
            // host listeners but skip the DB write.
            $this->snapshotRevision();
            $this->dispatch('page-studio:graph:saved', routeId: null);
            return;
        }
        $graph = NodeGraph::updateOrCreate(
            ['route_id' => $this->routeId],
            ['nodes' => $this->nodes, 'edges' => $this->edges],
        );
        $this->snapshotRevision();
        $this->dispatch('page-studio:graph:saved', routeId: $this->routeId);
        GraphSaved::dispatch($graph, auth()->user());
    }

    /**
     * Persist a revision of the page + graph so the user can restore an
     * earlier state. Capped at the most recent 30 revisions per route so
     * long-lived projects don't bloat the table.
     */
    protected function snapshotRevision(): void
    {
        // Revisions table is scoped per-route · in ephemeral / page-only
        // mode we have no route to attach the snapshot to, so skip.
        if ($this->routeId === null) return;

        \LoggedCloud\PageStudio\Models\Revision::create([
            'route_id'  => $this->routeId,
            'blocks'    => $this->blocks,
            'nodes'     => $this->nodes,
            'edges'     => $this->edges,
            'author_id' => auth()->id(),
        ]);

        $keep = \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)
            ->orderByDesc('id')->limit(30)->pluck('id');
        \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)
            ->whereNotIn('id', $keep)->delete();
    }

    public function restoreRevision(int $revisionId): void
    {
        $rev = \LoggedCloud\PageStudio\Models\Revision::find($revisionId);
        if (! $rev || $rev->route_id !== $this->routeId) return;
        $this->pushHistory();
        $this->blocks = BlockTree::sanitise((array) $rev->blocks);
        $this->nodes  = (array) $rev->nodes;
        $this->edges  = (array) $rev->edges;
        $this->selectedNodeId = null;
        $this->selectedPath   = '';
        $this->dispatch('page-studio:revision:restored', revisionId: $revisionId);
    }

    /**
     * Tiny diff between the current state and a stored revision · numbers
     * only (blocks +N / -N, nodes +N / -N, edges +N / -N) since rendering a
     * full text diff inside Livewire snapshots gets unwieldy.
     */
    public function diffRevision(int $revisionId): array
    {
        $rev = \LoggedCloud\PageStudio\Models\Revision::find($revisionId);
        if (! $rev || $rev->route_id !== $this->routeId) {
            return ['blocks' => 0, 'nodes' => 0, 'edges' => 0];
        }
        return [
            'blocks' => count($this->blocks) - count((array) $rev->blocks),
            'nodes'  => count($this->nodes)  - count((array) $rev->nodes),
            'edges'  => count($this->edges)  - count((array) $rev->edges),
        ];
    }

    /**
     * Live "dirty diff" stamp · compares the current in-memory state to the
     * most recent persisted revision. Returns zeros when no revision exists
     * yet (e.g. before the first save) so the topbar can simply check
     * if (any non-zero) → render.
     *
     * @return array{blocks:int,nodes:int,edges:int}
     */
    #[Computed]
    public function latestRevisionDiff(): array
    {
        if ($this->routeId === null) {
            return ['blocks' => 0, 'nodes' => 0, 'edges' => 0];
        }
        $latest = \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)
            ->orderByDesc('id')
            ->first();
        if (! $latest) {
            return ['blocks' => 0, 'nodes' => 0, 'edges' => 0];
        }
        return [
            'blocks' => count($this->blocks) - count((array) $latest->blocks),
            'nodes'  => count($this->nodes)  - count((array) $latest->nodes),
            'edges'  => count($this->edges)  - count((array) $latest->edges),
        ];
    }

    #[Computed]
    public function revisions(): array
    {
        return \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'created_at' => $r->created_at?->toIso8601String(),
                'blocks'     => count((array) $r->blocks),
                'nodes'      => count((array) $r->nodes),
            ])
            ->all();
    }

    /**
     * Richer revisions list used by the side-by-side compare overlay · 30
     * deepest history plus author + edge counts.
     *
     * @return array<int, array{id:int,created_at_iso:?string,author_name:?string,block_count:int,node_count:int,edge_count:int}>
     */
    #[Computed]
    public function revisionsList(): array
    {
        if ($this->routeId === null) return [];

        return \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(function ($r) {
                // author_name is best-effort · resolve the host app's User
                // model if it has a `name` column · null otherwise.
                $name = null;
                if ($r->author_id) {
                    try {
                        $userModel = config('auth.providers.users.model');
                        if ($userModel && class_exists($userModel)) {
                            $user = $userModel::find($r->author_id);
                            if ($user && isset($user->name)) $name = (string) $user->name;
                        }
                    } catch (\Throwable) {
                        // Best effort · never crash the panel.
                    }
                }

                return [
                    'id'              => (int) $r->id,
                    'created_at_iso'  => $r->created_at?->toIso8601String(),
                    'author_name'     => $name,
                    'block_count'     => count((array) $r->blocks),
                    'node_count'      => count((array) $r->nodes),
                    'edge_count'      => count((array) $r->edges),
                ];
            })
            ->all();
    }

    /**
     * Fetch two revisions on the bound route + a tiny numeric diff between
     * them. Returns ['a' => null, 'b' => null, 'diff' => ...] if either id
     * doesn't belong to the same route_id.
     *
     * @return array{a: ?\LoggedCloud\PageStudio\Models\Revision, b: ?\LoggedCloud\PageStudio\Models\Revision, diff: array{blocks:int,nodes:int,edges:int}}
     */
    public function compareRevisions(int $aId, int $bId): array
    {
        $empty = ['a' => null, 'b' => null, 'diff' => ['blocks' => 0, 'nodes' => 0, 'edges' => 0]];
        if ($this->routeId === null) return $empty;

        $a = \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)->find($aId);
        $b = \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)->find($bId);
        if (! $a || ! $b) return $empty;

        return [
            'a'    => $a,
            'b'    => $b,
            'diff' => [
                'blocks' => count((array) $b->blocks) - count((array) $a->blocks),
                'nodes'  => count((array) $b->nodes)  - count((array) $a->nodes),
                'edges'  => count((array) $b->edges)  - count((array) $a->edges),
            ],
        ];
    }

    /**
     * UI state for the compare-revisions overlay · null when closed, else
     * the two picked revision ids the modal is showing.
     */
    public bool $compareOpen = false;
    public ?int $compareAId = null;
    public ?int $compareBId = null;

    public function openCompare(): void
    {
        $list = $this->revisionsList();
        if (count($list) < 2) {
            // Need at least two snapshots to compare · open anyway with
            // whatever is available so the user sees a hint.
            $this->compareAId = $list[0]['id'] ?? null;
            $this->compareBId = $list[0]['id'] ?? null;
        } else {
            // Default to the two most recent revisions.
            $this->compareBId = $list[0]['id'];
            $this->compareAId = $list[1]['id'];
        }
        $this->compareOpen = true;
    }

    public function closeCompare(): void
    {
        $this->compareOpen = false;
    }

    /**
     * Rendered preview HTML for a side of the compare overlay · returns ''
     * when the revision id isn't on this route.
     */
    public function renderRevisionPreview(?int $revisionId): string
    {
        if ($revisionId === null || $this->routeId === null) return '';
        $rev = \LoggedCloud\PageStudio\Models\Revision::where('route_id', $this->routeId)->find($revisionId);
        if (! $rev) return '';
        return PageRenderer::render(
            BlockTree::sanitise((array) $rev->blocks),
            $this->variableContext(),
            true,
        );
    }

    protected function findNode(string $id): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['id'] === $id) return $node;
        }
        return null;
    }

    /**
     * Splice a `{{ name }}` token into the given wire-model path at the
     * supplied caret position. Server-side so it can't be lost to a
     * debounced wire:model not catching a synthetic DOM event.
     */
    public function insertVariable(string $prop, string $varName, ?int $start = null, ?int $end = null): void
    {
        // Whitelist the property path · only allow paths into the blocks tree
        // so a malicious client can't write to arbitrary public fields.
        if (! preg_match('/^blocks(\.[A-Za-z0-9_]+)+$/', $prop)) {
            return;
        }
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $varName)) {
            return;
        }

        $current = (string) (data_get($this, $prop) ?? '');
        $token   = '{{ '.$varName.' }}';

        // Caret-aware insert when the client passed positions; else append.
        if ($start !== null && $end !== null && $start >= 0 && $end >= $start && $end <= mb_strlen($current)) {
            $before = mb_substr($current, 0, $start);
            $after  = mb_substr($current, $end);
            $next   = $before.$token.$after;
        } else {
            $next = $current === '' ? $token : $current.' '.$token;
        }

        data_set($this, $prop, $next);
        $this->dispatch('page-studio:var:inserted',
            prop: $prop,
            varName: $varName,
            caret: ($start !== null ? $start + strlen($token) : null),
        );
    }

    public function save(): void
    {
        // Ephemeral mode · no binding to persist against. The author is
        // using the builder as a transient surface (e.g. passing custom
        // variables via Blade props) · still bounce a `saved` event so
        // host code can intercept the block tree.
        if ($this->pageId === null && $this->routeId === null) {
            $this->lastSavedAt = now()->toIso8601String();
            $this->snapshotRevision();
            $this->dispatch('page-studio:page:saved',
                routeId:     null,
                pageId:      null,
                savedAt:     $this->lastSavedAt,
                blocks:      $this->blocks,
                meta:        $this->meta,
                status:      $this->status,
                publishAt:   $this->publishAt,
                publishedAt: $this->publishedAt,
            );
            return;
        }

        $payload = [
            'blocks'     => BlockTree::sanitise($this->blocks),
            'meta'       => $this->meta,
            'status'     => $this->status,
            'publish_at' => $this->publishAt ?: null,
        ];
        // Only stamp published_at when we already hold one (e.g. publish()
        // set it just before calling save) · save() itself never auto-stamps.
        if ($this->publishedAt !== null) {
            $payload['published_at'] = $this->publishedAt;
        }
        $page = $this->pageId !== null
            ? tap(Page::find($this->pageId), function ($p) use ($payload) {
                if ($p) $p->update($payload);
            })
            : Page::updateOrCreate(['route_id' => $this->routeId], $payload);

        $this->lastSavedAt = $page?->updated_at?->toIso8601String();
        // Re-hydrate from the persisted row so casts apply (publish_at gets
        // normalised, published_at reflects what's actually in the DB).
        if ($page) {
            $this->publishAt   = $page->publish_at?->format('Y-m-d\TH:i');
            $this->publishedAt = $page->published_at?->toIso8601String();
            $this->status      = (string) ($page->status ?? $this->status);
        }
        $this->snapshotRevision();
        $this->dispatch('page-studio:page:saved',
            routeId:     $this->routeId,
            pageId:      $this->pageId,
            savedAt:     $this->lastSavedAt,
            meta:        $this->meta,
            status:      $this->status,
            publishAt:   $this->publishAt,
            publishedAt: $this->publishedAt,
        );
        if ($page) PageSaved::dispatch($page, auth()->user());
    }

    /**
     * Mark the page as published · stamps published_at to now and persists.
     */
    public function publish(): void
    {
        $this->status      = 'published';
        $this->publishedAt = now()->toIso8601String();
        $this->save();
    }

    /**
     * Flip a published page back to draft · leaves the prior published_at
     * stamp on the row for audit / "last published" UI later.
     */
    public function unpublish(): void
    {
        $this->status = 'draft';
        $this->save();
    }

    #[Computed]
    public function route(): ?RouteDefinition
    {
        if ($this->routeId === null) return null;
        return RouteDefinition::with('segments.variable')->find($this->routeId);
    }

    #[Computed]
    public function variables(): array
    {
        $vars = [];
        $seen = [];

        // 1. Caller-supplied custom variables · take precedence (highest in list).
        foreach ($this->customVariables as $cv) {
            $vars[] = [
                'name'    => $cv['name'],
                'label'   => $cv['label'],
                'type'    => 'custom',
                'source'  => 'caller',
                'preview' => $cv['preview'],
            ];
            $seen[$cv['name']] = true;
        }

        // 2. Route-derived variables (when bound to a route).
        $route = $this->route();
        foreach ($route?->segments ?? [] as $s) {
            if ($s->kind !== 'variable' || ! $s->variable) continue;
            if (isset($seen[$s->variable->name])) continue;
            $first = (array) $s->variable->examples;
            $vars[] = [
                'name'    => $s->variable->name,
                'label'   => $s->variable->label ?? $s->variable->name,
                'type'    => $s->variable->type,
                'source'  => 'route',
                'preview' => $first[0] ?? '',
            ];
            $seen[$s->variable->name] = true;
        }

        // 3. Variables produced by the node graph's Output nodes.
        $base   = $this->routeContext();
        $merged = NodeGraphEngine::evaluate($this->nodes, $this->edges, $base);
        foreach ($merged as $name => $value) {
            if (isset($seen[$name])) continue;
            $vars[] = [
                'name'    => $name,
                'label'   => $name,
                'type'    => 'node',
                'source'  => 'node',
                'preview' => is_scalar($value) || $value === null ? (string) $value : json_encode($value),
            ];
            $seen[$name] = true;
        }
        return $vars;
    }

    #[Computed]
    public function variableContext(): array
    {
        return $this->graphEvaluation()['context'];
    }

    /**
     * Per-node socket values from the most recent graph evaluation · used by
     * the editor to render the live value next to every output socket.
     *
     * @return array<string, array<string, mixed>>  nodeId → socketKey → value
     */
    #[Computed]
    public function nodeSocketValues(): array
    {
        return $this->graphEvaluation()['nodeOutputs'];
    }

    /**
     * 0-indexed toposort position per node · sources score lowest, downstream
     * outputs highest. The editor renders this as a small badge so the data
     * flow is recognisable at a glance.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function nodeOrder(): array
    {
        return $this->graphEvaluation()['order'] ?? [];
    }

    protected function graphEvaluation(): array
    {
        // When the drawer is closed and the user isn't on preview mode, skip
        // the expensive live evaluation that decorates the canvas. The
        // variables panel still needs the merged context though, so we
        // hand back the route context with empty node outputs.
        if (! $this->drawerOpen && ! $this->previewMode && empty($this->nodes)) {
            return ['context' => $this->routeContext(), 'nodeOutputs' => []];
        }
        return NodeGraphEngine::evaluateAll($this->nodes, $this->edges, $this->routeContext());
    }

    /**
     * Raw route-variable values · first example per variable · the base
     * context the node graph composes on top of.
     */
    protected function routeContext(): array
    {
        $ctx = [];

        // Caller-supplied variables come first so the route can override
        // them if the same name appears in segments · matches the lookup
        // order in `variables()`.
        foreach ($this->customVariables as $cv) {
            $ctx[$cv['name']] = $cv['preview'];
        }

        $route = $this->route();
        foreach ($route?->segments ?? [] as $s) {
            if ($s->kind !== 'variable' || ! $s->variable) continue;
            if (array_key_exists($s->variable->name, $ctx)) continue;
            $first = (array) $s->variable->examples;
            $ctx[$s->variable->name] = $first[0] ?? '';
        }
        return $ctx;
    }

    #[Computed]
    public function nodeLibrary(): array
    {
        $library  = config('page-studio.nodes', []);
        $disabled = array_flip((array) config('page-studio.disabled_nodes', []));

        $grouped = [];
        foreach ($library as $key => $def) {
            if (isset($disabled[$key])) continue;
            $g = $def['group'] ?? 'source';
            $grouped[$g][$key] = $def;
        }
        // Stable section order · sources, transforms, image, output, notes.
        $ordered = [];
        foreach (['source', 'transform', 'image', 'output', 'note'] as $g) {
            if (! empty($grouped[$g])) $ordered[$g] = $grouped[$g];
        }
        return $ordered;
    }

    #[Computed]
    public function selectedNode(): ?array
    {
        if ($this->selectedNodeId === null) return null;
        foreach ($this->nodes as $node) {
            if ($node['id'] === $this->selectedNodeId) return $node;
        }
        return null;
    }

    #[Computed]
    public function selectedNodeSchema(): array
    {
        $node = $this->selectedNode();
        if (! $node) return [];
        $library = config('page-studio.nodes', []);
        return $library[$node['type']] ?? [];
    }

    public function selectedNodeSettingsPrefix(): string
    {
        if ($this->selectedNodeId === null) return '';
        foreach ($this->nodes as $i => $node) {
            if ($node['id'] === $this->selectedNodeId) return "nodes.$i.settings.";
        }
        return '';
    }

    /**
     * Flat list of every layout block currently on the page · the right-click
     * "Move into..." submenu uses this to offer the available drop targets.
     * Returns one entry per (block, slot) pair so the caller can pass the
     * exact slot to moveBlock().
     *
     * @return array<int, array{path:string, slot:string, label:string}>
     */
    #[Computed]
    public function layoutTargets(): array
    {
        $out = [];
        $walk = function (array $list, string $path) use (&$walk, &$out) {
            foreach ($list as $i => $block) {
                $p      = $path === '' ? (string) $i : $path.'/'.$i;
                $schema = config('page-studio.blocks.'.($block['type'] ?? ''), []);
                if (! empty($schema['slots'])) {
                    foreach ($schema['slots'] as $slot => $slotLabel) {
                        $slotLabelStr = is_array($slotLabel) ? ($slotLabel['label'] ?? $slot) : $slotLabel;
                        $out[] = [
                            'path'  => $p,
                            'slot'  => $slot,
                            'label' => ($schema['label'] ?? $block['type']).' · '.$slotLabelStr,
                        ];
                    }
                }
                if (! empty($block['children']) && is_array($block['children'])) {
                    foreach ($block['children'] as $slot => $kids) {
                        if (! is_array($kids)) continue;
                        $walk($kids, $p.'/'.$slot);
                    }
                }
            }
        };
        $walk($this->blocks, '');
        return $out;
    }

    #[Computed]
    public function blockLibrary(): array
    {
        $library  = config('page-studio.blocks', []);
        $disabled = array_flip((array) config('page-studio.disabled_blocks', []));

        $grouped = [];
        foreach ($library as $key => $def) {
            if (isset($disabled[$key])) continue;
            // Email mode · drop blocks that declare themselves unsafe for
            // email clients (CSS grid layouts, color-mix backgrounds, ...).
            if ($this->emailMode && ($def['email_safe'] ?? true) === false) continue;
            $g = $def['group'] ?? 'content';
            $grouped[$g][$key] = $def;
        }
        return $grouped;
    }

    /**
     * True when the bottom-drawer node editor is enabled via config · used
     * by Blade to hide the "Show nodes" button + the drawer itself when a
     * host app wants pages-only authoring.
     */
    public function nodeEditorEnabled(): bool
    {
        return (bool) config('page-studio.enable_node_editor', true);
    }

    /**
     * Resolve the output socket list for a specific node · most node types
     * just return the static schema, but `source.model_finder` with
     * `expose_fields=true` expands one socket per column of the chosen
     * model. The Blade canvas + the engine both consult this so the UI
     * and the runtime stay in sync.
     */
    public function outputsFor(array $node): array
    {
        $library = config('page-studio.nodes', []);
        $schema  = $library[$node['type'] ?? ''] ?? [];
        $outputs = $schema['outputs'] ?? [];

        if (($node['type'] ?? '') === 'source.model_finder' && ! empty($node['settings']['expose_fields'])) {
            $fields = \LoggedCloud\PageStudio\Support\ModelFields::for(
                (string) ($node['settings']['model_class'] ?? ''),
            );
            if (! empty($fields)) {
                $outputs = [];
                foreach ($fields as $col => $type) {
                    $outputs[$col] = ['label' => $col, 'type' => $type];
                }
            }
        }

        return $outputs;
    }

    /**
     * Server action backing the model-finder header button · flips the
     * `expose_fields` flag for a node so the canvas + settings panel
     * reflect the change without the user opening the settings drawer.
     */
    public function toggleModelFields(string $nodeId): void
    {
        foreach ($this->nodes as $i => $node) {
            if ($node['id'] !== $nodeId) continue;
            if ($node['type'] !== 'source.model_finder') return;
            $this->pushHistory();
            $current = ! empty($node['settings']['expose_fields']);
            $this->nodes[$i]['settings']['expose_fields'] = ! $current;
            return;
        }
    }

    #[Computed]
    public function selectedBlock(): ?array
    {
        if ($this->selectedPath === '') return null;
        return BlockTree::get($this->blocks, $this->selectedPath);
    }

    #[Computed]
    public function selectedBlockSchema(): array
    {
        $block = $this->selectedBlock();
        if (! $block) return [];
        return config("page-studio.blocks.{$block['type']}.settings", []);
    }

    /**
     * The wire:model prefix for the currently-selected block's settings so
     * the settings panel can build absolute paths like
     * `blocks.0.children.body.1.settings.text`.
     */
    public function selectedSettingsPrefix(): string
    {
        if ($this->selectedPath === '') return '';
        $bits = array_values(array_filter(explode('/', $this->selectedPath), fn ($b) => $b !== ''));
        $prefix = 'blocks';
        foreach ($bits as $i => $b) {
            $prefix .= $i % 2 === 0 ? '.'.$b : '.children.'.$b;
        }
        return $prefix.'.settings.';
    }

    #[Computed]
    public function previewHtml(): string
    {
        return PageRenderer::render($this->blocks, $this->variableContext());
    }

    /**
     * Return the children of (parentPath, slot) · the root list when both
     * are empty.
     */
    protected function slotContents(string $parentPath, ?string $slot): array
    {
        if ($parentPath === '') return $this->blocks;
        $parent = BlockTree::get($this->blocks, $parentPath);
        return $parent['children'][$slot] ?? [];
    }

    protected function pathFor(string $parentPath, ?string $slot, int $index): string
    {
        if ($parentPath === '') return (string) $index;
        return $parentPath.'/'.$slot.'/'.$index;
    }

    public function render()
    {
        return view('page-studio::livewire.page-builder');
    }
}
