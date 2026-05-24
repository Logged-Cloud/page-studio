<div class="ps-vl" data-component="page-studio::variable-library">
    <div class="ps-vl-header">
        <h2>Variable library</h2>
        <input type="search" wire:model.live.debounce.250ms="search"
               placeholder="Filter by name…" class="ps-vl-search">
    </div>

    @error('delete') <p class="ps-rb-err">{{ $message }}</p> @enderror

    @if ($variables->isEmpty())
        <p class="ps-vl-empty">No variables yet · build a route and right-click a segment to create one.</p>
    @else
        <table class="ps-vl-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Examples</th>
                    <th>Used in</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($variables as $variable)
                    <tr>
                        <td><code>{{ '{'.$variable->name.'}' }}</code></td>
                        <td>{{ $variable->type }}</td>
                        <td>
                            @foreach (array_slice((array) $variable->examples, 0, 3) as $ex)
                                <span class="ps-vl-pill">{{ $ex }}</span>
                            @endforeach
                            @if (count((array) $variable->examples) > 3)
                                <span class="ps-vl-pill">+{{ count($variable->examples) - 3 }}</span>
                            @endif
                        </td>
                        <td>{{ $variable->segments_count }} route(s)</td>
                        <td>
                            <button type="button" wire:click="delete({{ $variable->id }})"
                                    class="ps-vl-btn ps-vl-btn--danger"
                                    @disabled($variable->segments_count > 0)>
                                Delete
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @once
        @push('scripts')
            <style>
                .ps-vl-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: .75rem;
                    margin-bottom: 1rem;
                }
                .ps-vl-header h2 { margin: 0; }
                .ps-vl-search {
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    padding: .4rem .65rem;
                    font: inherit;
                    min-width: 14rem;
                }
                .ps-vl-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
                .ps-vl-table th, .ps-vl-table td {
                    text-align: left;
                    padding: .55rem .65rem;
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-vl-table code {
                    font-family: ui-monospace, monospace;
                    background: rgba(255,255,255,.06);
                    padding: .1rem .35rem;
                    border-radius: .25rem;
                }
                .ps-vl-pill {
                    display: inline-block;
                    background: rgba(255,255,255,.05);
                    padding: .1rem .45rem;
                    border-radius: .25rem;
                    margin-right: .2rem;
                    font-family: ui-monospace, monospace;
                    font-size: .75rem;
                }
                .ps-vl-empty { color: var(--ink-dim, #A3A099); font-style: italic; }
                .ps-vl-btn {
                    background: transparent;
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5);
                    padding: .25rem .55rem;
                    border-radius: .25rem;
                    cursor: pointer;
                    font: inherit;
                    font-size: .75rem;
                }
                .ps-vl-btn--danger:not(:disabled) { color: var(--danger, #ef4444); border-color: var(--danger, #ef4444); }
                .ps-vl-btn:disabled { opacity: .4; cursor: not-allowed; }
            </style>
        @endpush
    @endonce
</div>
