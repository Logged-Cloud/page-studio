<div
    x-data="pageStudioRouteBuilder({
        initialSegments: @js($segments),
        minExamples: @js((int) config('page-studio.min_examples_per_variable', 3)),
    })"
    class="ps-route-builder"
    data-component="page-studio.route-builder"
>
    {{-- ─── Header: name + description · method is implicit (GET, page builder) ─── --}}
    <div class="ps-rb-header">
        <div class="ps-rb-field">
            <label>Route name</label>
            <input type="text" wire:model.live="name" placeholder="users.show">
            @error('name') <span class="ps-rb-err">{{ $message }}</span> @enderror
        </div>
        <div class="ps-rb-field ps-rb-field--desc">
            <label>Description (optional)</label>
            <input type="text" wire:model.live="description" placeholder="What this page is about">
        </div>
    </div>

    {{-- ─── Plain path input · auto-splits on / ─────────────────────────── --}}
    <div class="ps-rb-field">
        <label>URL path</label>
        <div class="ps-rb-path-row">
            <span class="ps-rb-path-prefix" aria-hidden="true">/</span>
            <input
                type="text"
                class="ps-rb-path-input"
                wire:model.live.debounce.300ms="rawPath"
                placeholder="users/123/posts/hello"
                spellcheck="false"
                autocomplete="off"
            >
        </div>
        <p class="ps-rb-hint">
            Slashes split the path. Right-click any chip below to turn it into a variable.
        </p>
    </div>

    {{-- ─── Segment chips · click any chip to open its menu ──────────────── --}}
    <div class="ps-rb-chips" role="list" aria-label="Parsed segments">
        <template x-if="segments.length === 0">
            <span class="ps-rb-chips-empty">Type a path above to see segments.</span>
        </template>
        <template x-for="(seg, i) in segments" :key="`${i}-${seg.kind}-${seg.value}`">
            <span class="ps-rb-chip-wrap" role="listitem">
                <span class="ps-rb-slash" x-show="i > 0" aria-hidden="true">/</span>
                <span class="ps-rb-chip-pop">
                    <button
                        type="button"
                        class="ps-rb-chip"
                        :class="seg.kind === 'variable' ? 'is-variable' : 'is-literal'"
                        @click.stop="toggleMenu(i)"
                        @contextmenu.prevent.stop="toggleMenu(i)"
                        @touchstart="onTouchStart(i, $event)"
                        @touchend="onTouchEnd()"
                        :aria-haspopup="'menu'"
                        :aria-expanded="openMenuIndex === i ? 'true' : 'false'"
                        x-text="seg.kind === 'variable' ? '{' + seg.value + '}' : seg.value"
                    ></button>

                    <div class="ps-rb-context-menu" role="menu"
                         x-show="openMenuIndex === i" x-cloak
                         @click.outside="closeMenu()"
                         @keydown.escape.window="closeMenu()">
                        <button type="button" role="menuitem"
                                x-show="seg.kind === 'literal'"
                                @click="turnIntoVariable(i)">
                            ↻ Turn into variable
                        </button>
                        <button type="button" role="menuitem"
                                x-show="seg.kind === 'variable'"
                                @click="editVariable(i)">
                            ✎ Edit variable rules
                        </button>
                        <button type="button" role="menuitem"
                                x-show="seg.kind === 'variable'"
                                @click="turnIntoLiteral(i)">
                            ↺ Turn back into literal
                        </button>
                        <button type="button" role="menuitem"
                                class="ps-rb-context-danger"
                                @click="removeSegment(i)">
                            ✕ Remove segment
                        </button>
                    </div>
                </span>
            </span>
        </template>
    </div>

    <p class="ps-rb-preview">
        <span class="ps-rb-preview-label">Compiled:</span>
        <code x-text="compiledTemplate"></code>
    </p>

    {{-- ─── Variable definition panel ────────────────────────────────── --}}
    @if ($showVariablePanel)
        <div class="ps-rb-panel" role="dialog" aria-label="Variable definition">
            <header>
                <h3>Define variable</h3>
                <button type="button" wire:click="closeVariablePanel" aria-label="Close">✕</button>
            </header>

            <div class="ps-rb-field">
                <label>Name</label>
                <input type="text" wire:model.live="newVariable.name">
                @error('newVariable.name') <span class="ps-rb-err">{{ $message }}</span> @enderror
            </div>

            <div class="ps-rb-field">
                <label>Type</label>
                <select wire:model.live="newVariable.type">
                    @foreach ($variableTypes as $key => $cfg)
                        <option value="{{ $key }}">{{ $cfg['label'] ?? $key }}</option>
                    @endforeach
                </select>
            </div>

            @if ($newVariable['type'] === 'custom')
                <div class="ps-rb-field">
                    <label>Custom regex (no anchors)</label>
                    <input type="text" wire:model.live="newVariable.regex" placeholder="[a-z]{3}-\d+">
                    @error('newVariable.regex') <span class="ps-rb-err">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="ps-rb-field">
                <label>Label (optional)</label>
                <input type="text" wire:model.live="newVariable.label" placeholder="User ID">
            </div>

            <div class="ps-rb-field">
                <label>Description (optional)</label>
                <input type="text" wire:model.live="newVariable.description"
                       placeholder="What this variable represents">
            </div>

            <div class="ps-rb-field">
                <label>Examples
                    <span class="ps-rb-hint">(at least {{ config('page-studio.min_examples_per_variable', 3) }})</span>
                </label>
                @foreach ($newVariable['examples'] as $i => $ex)
                    <div class="ps-rb-example-row">
                        <input type="text" wire:model.live="newVariable.examples.{{ $i }}"
                               placeholder="example value">
                        @error("newVariable.examples.$i") <span class="ps-rb-err">{{ $message }}</span> @enderror
                    </div>
                @endforeach
                <button type="button" class="ps-rb-add-example"
                        @click="$wire.set('newVariable.examples.' + $wire.newVariable.examples.length, '')">
                    + Add another example
                </button>
                @error('newVariable.examples') <span class="ps-rb-err">{{ $message }}</span> @enderror
            </div>

            <footer>
                <button type="button" wire:click="closeVariablePanel" class="ps-rb-btn">Cancel</button>
                <button type="button" wire:click="commitVariable" class="ps-rb-btn ps-rb-btn--primary">
                    Save variable
                </button>
            </footer>
        </div>
    @endif

    {{-- ─── Save ─────────────────────────────────────────────────────── --}}
    <div class="ps-rb-footer">
        <button type="button" wire:click="save" class="ps-rb-btn ps-rb-btn--primary">
            Save route
        </button>
    </div>

    @if (! empty($this->compiled))
        <div class="ps-rb-compiled" x-data x-cloak>
            <h4>Example URLs</h4>
            <ul>
                @foreach ($this->compiled['examples'] ?? [] as $ex)
                    <li><code>{{ $ex }}</code></li>
                @endforeach
            </ul>
            @if (! empty($this->compiled['where']))
                <h4>where() constraints</h4>
                <pre><code>{{ json_encode($this->compiled['where'], JSON_PRETTY_PRINT) }}</code></pre>
            @endif
        </div>
    @endif

    @once
        @push('scripts')
            <script>
                window.pageStudioRouteBuilder = function ({ initialSegments, minExamples }) {
                    return {
                        segments: initialSegments,
                        // Index of the chip whose menu is open. -1 = no menu.
                        // Per-chip popovers replace the absolute cursor-positioned
                        // menu so right-click works the same on desktop + touch.
                        openMenuIndex: -1,
                        touchTimer: null,

                        get compiledTemplate() {
                            if (! this.segments.length) return '/';
                            return '/' + this.segments.map((s) =>
                                s.kind === 'variable' ? '{' + s.value + '}' : s.value
                            ).join('/');
                        },

                        init() {
                            // Mirror Livewire-side segment updates back into Alpine.
                            this.$watch(() => this.$wire.segments, (v) => {
                                this.segments = v || [];
                                this.openMenuIndex = -1;
                            });
                        },

                        toggleMenu(i) {
                            this.openMenuIndex = this.openMenuIndex === i ? -1 : i;
                        },
                        closeMenu() {
                            this.openMenuIndex = -1;
                        },

                        onTouchStart(i, e) {
                            // Long-press = open the menu directly on touch.
                            this.touchTimer = setTimeout(() => this.openMenuIndex = i, 450);
                        },
                        onTouchEnd() {
                            if (this.touchTimer) clearTimeout(this.touchTimer);
                            this.touchTimer = null;
                        },

                        turnIntoVariable(i) {
                            this.closeMenu();
                            this.$wire.openVariablePanelFor(i);
                        },
                        editVariable(i) {
                            this.closeMenu();
                            this.$wire.openVariablePanelFor(i);
                        },
                        turnIntoLiteral(i) {
                            this.closeMenu();
                            this.$wire.convertSegmentToLiteral(i);
                        },
                        removeSegment(i) {
                            this.closeMenu();
                            this.$wire.removeSegment(i);
                        },
                    };
                };
            </script>
        @endpush
        @push('scripts')
            <style>
                .ps-route-builder { position: relative; font-family: inherit; }
                .ps-rb-header {
                    display: grid;
                    grid-template-columns: 1fr 2fr;
                    gap: .75rem;
                    margin-bottom: 1rem;
                }
                .ps-rb-field { margin-bottom: .85rem; }
                .ps-rb-field label {
                    display: block;
                    font-size: .75rem;
                    color: var(--ink-dim, #A3A099);
                    margin-bottom: .2rem;
                }
                .ps-rb-field input, .ps-rb-field select {
                    width: 100%;
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    padding: .45rem .6rem;
                    font: inherit;
                    box-sizing: border-box;
                }
                .ps-rb-path-row {
                    display: flex;
                    align-items: stretch;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    overflow: hidden;
                }
                .ps-rb-path-row:focus-within {
                    border-color: var(--accent, #2C66E8);
                }
                .ps-rb-path-prefix {
                    padding: .45rem .55rem;
                    color: var(--ink-dim, #A3A099);
                    font-family: ui-monospace, monospace;
                    background: rgba(255,255,255,.03);
                    border-right: 1px solid var(--line, #3A3D40);
                    user-select: none;
                }
                .ps-rb-path-input {
                    flex: 1;
                    background: transparent;
                    color: var(--ink, #F0EDE5);
                    border: 0;
                    outline: none;
                    padding: .45rem .65rem;
                    font: inherit;
                    font-family: ui-monospace, monospace;
                }
                .ps-rb-hint {
                    margin: .3rem 0 0;
                    font-size: .75rem;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-rb-chips {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: center;
                    gap: .15rem;
                    padding: .65rem .75rem;
                    background: rgba(255,255,255,.03);
                    border: 1px dashed var(--line, #3A3D40);
                    border-radius: .35rem;
                    min-height: 2.6rem;
                    font-family: ui-monospace, monospace;
                    font-size: .95rem;
                }
                .ps-rb-chips-empty {
                    color: var(--ink-dim, #A3A099);
                    font-style: italic;
                    font-family: inherit;
                    font-size: .85rem;
                }
                .ps-rb-chip-wrap { display: inline-flex; align-items: center; gap: .15rem; }
                .ps-rb-chip {
                    display: inline-block;
                    padding: .25rem .6rem;
                    border-radius: .3rem;
                    background: rgba(255,255,255,.05);
                    color: var(--ink, #F0EDE5);
                    cursor: pointer;
                    user-select: none;
                    transition: background-color .15s;
                }
                .ps-rb-chip:hover, .ps-rb-chip:focus {
                    background: rgba(255,255,255,.12);
                    outline: none;
                }
                .ps-rb-chip.is-variable {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 22%, transparent);
                    color: var(--accent, #2C66E8);
                    font-weight: 600;
                }
                .ps-rb-chip.is-variable:hover, .ps-rb-chip.is-variable:focus {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 32%, transparent);
                }
                .ps-rb-slash { color: var(--ink-dim, #A3A099); user-select: none; }
                .ps-rb-preview {
                    font-size: .85rem;
                    color: var(--ink-dim, #A3A099);
                    margin: .65rem 0;
                }
                .ps-rb-preview code {
                    font-family: ui-monospace, monospace;
                    color: var(--ink, #F0EDE5);
                    background: rgba(255,255,255,.05);
                    padding: .1rem .4rem;
                    border-radius: .25rem;
                }
                .ps-rb-chip-pop {
                    position: relative;
                    display: inline-block;
                }
                .ps-rb-chip {
                    /* Override default button appearance · the chip is now a
                       real <button> for keyboard + a11y. */
                    background: rgba(255,255,255,.05);
                    border: 0;
                    font: inherit;
                    font-size: inherit;
                }
                .ps-rb-context-menu {
                    position: absolute;
                    top: calc(100% + .25rem);
                    left: 0;
                    z-index: 50;
                    min-width: 12rem;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 8px 24px rgba(0,0,0,.35);
                    padding: .25rem;
                    display: flex;
                    flex-direction: column;
                }
                .ps-rb-context-menu button {
                    background: transparent;
                    border: 0;
                    color: var(--ink, #F0EDE5);
                    text-align: left;
                    padding: .4rem .6rem;
                    border-radius: .25rem;
                    cursor: pointer;
                    font: inherit;
                    font-size: .85rem;
                }
                .ps-rb-context-menu button:hover {
                    background: rgba(255,255,255,.06);
                }
                .ps-rb-context-danger { color: var(--danger, #ef4444) !important; }
                .ps-rb-panel {
                    margin-top: 1rem;
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .5rem;
                    padding: 1rem;
                    background: var(--surface, #1A1B1E);
                }
                .ps-rb-panel header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: .75rem;
                }
                .ps-rb-panel header h3 { margin: 0; font-size: 1rem; }
                .ps-rb-panel header button {
                    background: transparent;
                    border: 0;
                    color: var(--ink-dim, #A3A099);
                    cursor: pointer;
                    font-size: 1rem;
                }
                .ps-rb-example-row { margin-bottom: .35rem; }
                .ps-rb-add-example {
                    background: transparent;
                    border: 1px dashed var(--line, #3A3D40);
                    color: var(--ink-dim, #A3A099);
                    padding: .3rem .6rem;
                    border-radius: .3rem;
                    cursor: pointer;
                    font: inherit;
                    font-size: .8rem;
                }
                .ps-rb-err { display: block; color: var(--danger, #ef4444); font-size: .75rem; margin-top: .2rem; }
                .ps-rb-panel footer {
                    display: flex;
                    gap: .5rem;
                    justify-content: flex-end;
                    margin-top: .75rem;
                }
                .ps-rb-footer {
                    display: flex;
                    gap: .5rem;
                    justify-content: flex-end;
                    margin-top: 1rem;
                }
                .ps-rb-btn {
                    background: transparent;
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5);
                    padding: .45rem .9rem;
                    border-radius: .35rem;
                    cursor: pointer;
                    font: inherit;
                }
                .ps-rb-btn--primary {
                    background: var(--accent, #2C66E8);
                    border-color: var(--accent, #2C66E8);
                    color: white;
                }
                .ps-rb-compiled {
                    margin-top: 1rem;
                    padding: .75rem 1rem;
                    background: rgba(255,255,255,.04);
                    border-radius: .35rem;
                    border-left: 3px solid var(--accent, #2C66E8);
                }
                .ps-rb-compiled h4 { margin: .25rem 0 .35rem; font-size: .85rem; color: var(--ink-dim, #A3A099); }
                .ps-rb-compiled ul { margin: 0; padding-left: 1.2rem; font-family: ui-monospace, monospace; font-size: .85rem; }
                .ps-rb-compiled pre { font-size: .8rem; background: rgba(0,0,0,.3); padding: .5rem; border-radius: .25rem; overflow: auto; }
            </style>
        @endpush
    @endonce
</div>
