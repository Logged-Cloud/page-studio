<div
    x-data="pageStudioPageBuilder()"
    @page-studio:page:saved.window="showToast('Page saved', true)"
    @page-studio:graph:saved.window="showToast('Graph autosaved', true)"
    @page-studio:graph:copied.window="showToast('Copied ' + ($event.detail.count || 0) + ' nodes', true)"
    @page-studio:replace:done.window="showToast(($event.detail.count || 0) + ' blocks updated', true)"
    @page-studio:lock:denied.window="showToast('Block is being edited by ' + ($event.detail.holder || 'someone else'), false)"
    class="ps-page-builder"
    data-component="page-studio.page-builder"
>
    {{-- ─── Top bar · rail toggles + route info + preview / save ───────────── --}}
    <div class="ps-pb-topbar">
        <button type="button"
                class="ps-pb-rail-toggle"
                @click="leftCollapsed = ! leftCollapsed"
                :aria-label="leftCollapsed ? 'Show components rail' : 'Hide components rail'"
                :aria-pressed="leftCollapsed ? 'true' : 'false'"
                :title="leftCollapsed ? 'Show components' : 'Hide components'">
            <span x-show="! leftCollapsed" aria-hidden="true">◀</span>
            <span x-show="leftCollapsed" x-cloak aria-hidden="true">▶</span>
        </button>

        @php $route = $this->route; @endphp
        <div class="ps-pb-route">
            <span class="ps-pb-method">GET</span>
            <code class="ps-pb-path">{{ $route?->path_template ?? '/' }}</code>
        </div>

        <div class="ps-pb-actions">
            {{-- Last-saved indicator · empty until the first save. --}}
            @if ($lastSavedAt)
                <span class="ps-pb-saved-stamp"
                      x-data="{ rel: '' }"
                      x-init="
                          const fmt = () => {
                              const d = new Date('{{ $lastSavedAt }}');
                              const diff = (Date.now() - d.getTime()) / 1000;
                              if (diff < 5)   rel = 'saved just now';
                              else if (diff < 60)  rel = `saved ${Math.floor(diff)}s ago`;
                              else if (diff < 3600) rel = `saved ${Math.floor(diff/60)}m ago`;
                              else rel = 'saved ' + d.toLocaleTimeString();
                          };
                          fmt();
                          setInterval(fmt, 5000);
                      "
                      x-text="rel"
                      :title="new Date('{{ $lastSavedAt }}').toLocaleString()"></span>
            @endif

            {{-- Live "dirty diff" stamp · how far the current state has
                 drifted from the most recent revision. Hidden when clean. --}}
            @php $lrd = $this->latestRevisionDiff; @endphp
            @if ($lrd['blocks'] !== 0 || $lrd['nodes'] !== 0 || $lrd['edges'] !== 0)
                <span class="ps-pb-diff-stamp"
                      title="Unsaved changes since the last revision">
                    @php
                        $parts = [];
                        foreach (['blocks' => 'blocks', 'nodes' => 'nodes', 'edges' => 'edges'] as $k => $label) {
                            if ($lrd[$k] === 0) continue;
                            $parts[] = ($lrd[$k] > 0 ? '+' : '').$lrd[$k].' '.$label;
                        }
                    @endphp
                    {{ implode(', ', $parts) }}
                </span>
            @endif

            {{-- Presence · "who else is here" pills next to the diff stamp.
                 Hidden when nobody else is on the page so single-author
                 editing stays visually quiet. --}}
            @php $peers = $this->activePeers; @endphp
            @if (! empty($peers))
                <span class="ps-pb-presence" title="Other people viewing this page">
                    <span class="ps-pb-presence-label">Viewing:</span>
                    @foreach ($peers as $peer)
                        @php
                            $initials = collect(explode(' ', trim($peer['name'])))
                                ->filter()
                                ->map(fn ($p) => mb_substr($p, 0, 1))
                                ->take(2)
                                ->implode('');
                            $initials = $initials !== '' ? mb_strtoupper($initials) : '?';
                        @endphp
                        <span class="ps-pb-presence-chip"
                              title="{{ $peer['name'] }} · last seen {{ $peer['last_seen'] }}">{{ $initials }}</span>
                    @endforeach
                </span>
            @endif

            <button type="button"
                    class="ps-pb-btn"
                    onclick="window.dispatchEvent(new KeyboardEvent('keydown', { key: '?' }))"
                    aria-label="Show keyboard shortcuts"
                    title="Keyboard shortcuts (?)">?</button>

            <button type="button"
                    class="ps-pb-btn"
                    @click="libraryOpen = true"
                    title="Snippet library · rename or delete saved snippets">
                ★ Library
            </button>

            {{-- Comments overview · jumps the right rail to the thread
                 view. Only shown when the page is bound (pageId or
                 routeId · ephemeral mode has no DB row to anchor to). --}}
            @if ($pageId !== null || $routeId !== null)
                @php $openCount = $this->openCommentsCount; @endphp
                <button type="button"
                        wire:click="showCommentsView"
                        class="ps-pb-btn ps-pb-comments-btn"
                        :class="$wire.rightRailView === 'comments' ? 'is-active' : ''"
                        title="Review comments on this page">
                    💬 {{ $openCount }}
                </button>
            @endif

            @if ($this->nodeEditorEnabled())
                <button type="button" wire:click="toggleDrawer"
                        class="ps-pb-btn ps-pb-nodes-btn"
                        :class="$wire.drawerOpen ? 'is-active' : ''">
                    <span class="ps-pb-nodes-icon" aria-hidden="true">⌘</span>
                    {{ $drawerOpen ? 'Hide nodes' : 'Show nodes' }}
                </button>
            @endif

            <button type="button" wire:click="togglePreview"
                    class="ps-pb-btn"
                    :class="$wire.previewMode ? 'is-active' : ''">
                {{ $previewMode ? '◀ Edit' : '👁 Preview' }}
            </button>

            {{-- Status badge · Draft / Scheduled (publish_at in the future) / Published --}}
            @php
                $isScheduled = $status === 'published'
                    && $publishAt
                    && \Illuminate\Support\Carbon::parse($publishAt)->isFuture();
                $badgeKind = $isScheduled ? 'scheduled' : ($status === 'published' ? 'published' : 'draft');
                $badgeLabel = ['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'][$badgeKind];
            @endphp
            <span class="ps-pb-status-badge ps-pb-status-badge--{{ $badgeKind }}"
                  title="Lifecycle state">{{ $badgeLabel }}</span>

            {{-- Optional scheduled publish · datetime-local picker --}}
            <label class="ps-pb-publish-at"
                   title="Publish at (leave empty to publish immediately when you click Publish)">
                <span class="ps-pb-publish-at-label" aria-hidden="true">⏲</span>
                <span class="ps-pb-visually-hidden">Publish at</span>
                <input type="datetime-local"
                       wire:model.live="publishAt"
                       aria-label="Publish at (leave empty to publish immediately)"
                       class="ps-pb-publish-at-input">
            </label>

            @if ($status === 'published')
                <button type="button"
                        wire:click="unpublish"
                        wire:target="unpublish"
                        wire:loading.attr="disabled"
                        class="ps-pb-btn">
                    <span wire:loading.remove wire:target="unpublish">Unpublish</span>
                    <span wire:loading wire:target="unpublish">Saving…</span>
                </button>
            @else
                <button type="button"
                        wire:click="publish"
                        wire:target="publish"
                        wire:loading.attr="disabled"
                        class="ps-pb-btn ps-pb-btn--primary">
                    <span wire:loading.remove wire:target="publish">Publish</span>
                    <span wire:loading wire:target="publish">Saving…</span>
                </button>
            @endif

            <button type="button"
                    wire:click="save"
                    wire:target="save"
                    wire:loading.attr="disabled"
                    wire:loading.class="is-saving"
                    class="ps-pb-btn ps-pb-save-btn">
                <span wire:loading.remove wire:target="save">Save page</span>
                <span wire:loading wire:target="save" class="ps-pb-save-busy">
                    <span class="ps-pb-spinner" aria-hidden="true"></span>
                    Saving…
                </span>
            </button>
            <button type="button"
                    class="ps-pb-rail-toggle"
                    @click="rightCollapsed = ! rightCollapsed"
                    :aria-label="rightCollapsed ? 'Show settings rail' : 'Hide settings rail'"
                    :aria-pressed="rightCollapsed ? 'true' : 'false'"
                    :title="rightCollapsed ? 'Show settings' : 'Hide settings'">
                <span x-show="! rightCollapsed" aria-hidden="true">▶</span>
                <span x-show="rightCollapsed" x-cloak aria-hidden="true">◀</span>
            </button>
        </div>
    </div>

    {{-- Email meta · subject / preheader / reply-to · shown in email mode --}}
    @if ($emailMode && ! $previewMode)
        <div class="ps-pb-email-meta">
            <label class="ps-pb-email-meta-field">
                <span>Subject</span>
                <input type="text"
                       wire:model.live.debounce.400ms="meta.subject"
                       placeholder="Subject line shown in the inbox">
            </label>
            <label class="ps-pb-email-meta-field">
                <span>Preheader</span>
                <input type="text"
                       wire:model.live.debounce.400ms="meta.preheader"
                       placeholder="Preview snippet (hidden in the email body)">
            </label>
            <label class="ps-pb-email-meta-field">
                <span>Reply to</span>
                <input type="email"
                       wire:model.live.debounce.400ms="meta.replyTo"
                       placeholder="address@example.com">
            </label>
        </div>
    @endif

    @if ($previewMode)
        <div class="ps-pb-preview-wrap"
             x-data="{ device: localStorage.getItem('psPbDevice') || 'desktop' }"
             x-init="$watch('device', v => localStorage.setItem('psPbDevice', v))">
            <div class="ps-pb-preview-toolbar">
                <button type="button" :class="device === 'phone'   ? 'is-active' : ''"
                        @click="device = 'phone'"   title="Phone (375 px)">📱 Phone</button>
                <button type="button" :class="device === 'tablet'  ? 'is-active' : ''"
                        @click="device = 'tablet'"  title="Tablet (768 px)">▭ Tablet</button>
                <button type="button" :class="device === 'desktop' ? 'is-active' : ''"
                        @click="device = 'desktop'" title="Desktop · full width">🖥 Desktop</button>
            </div>
            <div class="ps-pb-preview-pane"
                 :class="'ps-pb-preview-pane--' + device">
                @if (empty($blocks))
                    <p class="ps-pb-empty">No blocks yet. Switch back to Edit to add some.</p>
                @else
                    {!! $this->previewHtml !!}
                @endif
            </div>
        </div>
    @else
        <div class="ps-pb-grid"
             :class="(leftCollapsed ? 'is-left-collapsed ' : '')
                + ((rightCollapsed || (! $wire.selectedPath && ! $wire.selectedNodeId)) ? 'is-right-collapsed' : '')">

            {{-- ─── LEFT · components grouped + variables panel ─── --}}
            <aside class="ps-pb-rail ps-pb-rail--left"
                   x-show="! leftCollapsed" x-cloak>
                @foreach ($this->blockLibrary as $group => $items)
                    <section class="ps-pb-section">
                        <h3>{{ ucfirst($group) }}</h3>
                        <div class="ps-pb-palette">
                            @foreach ($items as $type => $def)
                                <button type="button"
                                        class="ps-pb-palette-item"
                                        draggable="true"
                                        @dragstart.stop="onPaletteDragStart($event, @js($type))"
                                        @pointerdown="startTouchDrag($event, 'palette', @js($type), @js($def['label']))"
                                        @click="$wire.addBlock(@js($type))"
                                        title="Drag onto the canvas or click to append">
                                    <span class="ps-pb-palette-icon">{{ $def['icon'] }}</span>
                                    <span>{{ $def['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Snippet library · saved blocks the author can drop into any
                     page. Click appends to root, drag drops at the target. --}}
                @php $snips = $this->snippetLibrary; @endphp
                @if (! empty($snips))
                    @php
                        $snipGroups = [];
                        foreach ($snips as $s) { $snipGroups[$s['group']][] = $s; }
                    @endphp
                    @foreach ($snipGroups as $group => $items)
                        <section class="ps-pb-section ps-pb-snippets">
                            <h3>{{ ucfirst($group) }}</h3>
                            <div class="ps-pb-palette">
                                @foreach ($items as $s)
                                    <button type="button"
                                            class="ps-pb-palette-item"
                                            draggable="true"
                                            wire:key="snip-{{ $s['id'] }}"
                                            @dragstart.stop="onSnippetDragStart($event, @js($s['name']))"
                                            @pointerdown="startTouchDrag($event, 'snippet', @js($s['name']), @js($s['label']))"
                                            @click="$wire.dropSnippet(@js($s['name']))"
                                            :title="@js('Drop ' . $s['label'] . ' · drag onto the canvas or click to append')">
                                        <span class="ps-pb-palette-icon">{{ $s['icon'] }}</span>
                                        <span>{{ $s['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif

                {{-- Outline · a tree of the current page's block hierarchy. --}}
                @if (! empty($blocks))
                    <section class="ps-pb-section">
                        <h3>Outline</h3>
                        <div class="ps-pb-outline">
                            @php
                                $selectedPath = $this->selectedPath;
                                $renderOutline = function ($blocks, string $parentPath = '', ?string $slot = null, int $depth = 0) use (&$renderOutline, $selectedPath) {
                                    $out = '';
                                    foreach ($blocks as $i => $block) {
                                        $path = $parentPath === ''
                                            ? (string) $i
                                            : $parentPath.'/'.$slot.'/'.$i;
                                        $schema = (config('page-studio.blocks', []))[$block['type']] ?? [];
                                        $label  = $schema['label'] ?? $block['type'];
                                        $snippet = '';
                                        foreach (['text', 'label', 'title', 'src', 'value'] as $key) {
                                            if (! empty($block['settings'][$key])) {
                                                $snippet = (string) $block['settings'][$key];
                                                break;
                                            }
                                        }
                                        if (mb_strlen($snippet) > 32) $snippet = mb_substr($snippet, 0, 30).'…';

                                        $out .= '<button type="button" '
                                            .'wire:click="selectBlock(\''.htmlspecialchars($path, ENT_QUOTES).'\')" '
                                            .'class="ps-pb-outline-row '.($selectedPath === $path ? 'is-selected' : '').'" '
                                            .'style="padding-left:'.(0.5 + $depth * 0.85).'rem">'
                                            .'<span class="ps-pb-outline-icon">'.htmlspecialchars($schema['icon'] ?? '·', ENT_QUOTES).'</span>'
                                            .'<span class="ps-pb-outline-label">'.htmlspecialchars($label, ENT_QUOTES).'</span>'
                                            .($snippet !== '' ? '<span class="ps-pb-outline-snippet">'.htmlspecialchars($snippet, ENT_QUOTES).'</span>' : '')
                                            .'</button>';

                                        foreach ($schema['slots'] ?? [] as $slotKey => $_slotLabel) {
                                            $kids = $block['children'][$slotKey] ?? [];
                                            if (! empty($kids)) {
                                                $out .= $renderOutline($kids, $path, $slotKey, $depth + 1);
                                            }
                                        }
                                    }
                                    return $out;
                                };
                            @endphp
                            {!! $renderOutline($blocks) !!}
                        </div>
                    </section>
                @endif

                <section class="ps-pb-section">
                    <h3>Variables</h3>
                    @if (empty($this->variables))
                        <p class="ps-pb-hint">No variables on this route yet.</p>
                    @else
                        <div class="ps-pb-vars">
                            @foreach ($this->variables as $v)
                                <span
                                    class="ps-pb-var-chip"
                                    draggable="true"
                                    @dragstart.stop="onVarDragStart($event, @js($v['name']))"
                                    @dragend="onVarDragEnd($event)"
                                    @contextmenu.prevent="insertVarAsNode(@js($v['name']))"
                                    title="Drag into a text field, drag onto the node canvas, or right-click to insert as a source node"
                                >&#123;&#123; {{ $v['name'] }} &#125;&#125;</span>
                            @endforeach
                        </div>
                    @endif
                </section>
            </aside>

            {{-- ─── CENTRE · canvas ─── --}}
            <main class="ps-pb-canvas-wrap">
                <div class="ps-pb-canvas"
                     @click.self="$wire.clearSelection()"
                     @dragover.prevent="onCanvasDragOver($event)"
                     @dragleave="onCanvasLeave($event)"
                     @drop.prevent="onCanvasDrop($event)">

                    @if (empty($blocks))
                        <div class="ps-pb-canvas-empty"
                             :class="dropTarget.parentPath === '' && dropTarget.index === 0 ? 'is-drop-target' : ''">
                            <p>Drag a component from the left to start building.</p>
                        </div>
                    @endif

                    @php $varCtx = $this->variableContext; @endphp

                    {{-- drop indicator above the first root block --}}
                    <div class="ps-pb-drop-line"
                         x-show="dropTarget.parentPath === '' && dropTarget.index === 0 && segmentCount > 0"
                         x-cloak></div>

                    @foreach ($blocks as $i => $block)
                        @include('page-studio::livewire._block-editor', [
                            'block'      => $block,
                            'path'       => (string) $i,
                            'parentPath' => '',
                            'slot'       => null,
                            'index'      => $i,
                            'varCtx'     => $varCtx,
                        ])
                        <div class="ps-pb-drop-line"
                             x-show="dropTarget.parentPath === '' && dropTarget.index === {{ $i + 1 }}"
                             x-cloak></div>
                    @endforeach
                </div>
            </main>

            {{-- ─── RIGHT · settings + comments + activity panel ─── --}}
            <aside class="ps-pb-rail ps-pb-rail--right"
                   x-show="! rightCollapsed && ($wire.selectedPath || $wire.selectedNodeId || rightTab !== 'settings')" x-cloak>

                {{-- Tab strip · Settings / Comments / Activity all share the
                     rail. Selecting a non-Settings tab keeps the rail open
                     even when no block is selected. --}}
                <div class="ps-pb-rail-tab-strip">
                    <button type="button"
                            class="ps-pb-rail-tab"
                            :class="rightTab === 'settings' ? 'is-active' : ''"
                            @click="rightTab = 'settings'">Settings</button>
                    @if ($pageId !== null || $routeId !== null)
                        <button type="button"
                                class="ps-pb-rail-tab"
                                :class="rightTab === 'comments' ? 'is-active' : ''"
                                @click="rightTab = 'comments'">
                            💬 Comments
                            @if ($this->openCommentsCount > 0)
                                <span class="ps-pb-rail-tab-pip">{{ $this->openCommentsCount }}</span>
                            @endif
                        </button>
                    @endif
                    <button type="button"
                            class="ps-pb-rail-tab"
                            :class="rightTab === 'activity' ? 'is-active' : ''"
                            @click="rightTab = 'activity'">Activity</button>
                </div>

                <section class="ps-pb-section" x-show="rightTab === 'comments'" x-cloak>
                    @include('page-studio::livewire._comments-panel')
                </section>

                <section class="ps-pb-section" x-show="rightTab === 'activity'" x-cloak>
                    <h3>Activity</h3>
                    @php $feed = $this->activityFeed; @endphp
                    @if (empty($feed))
                        <p class="ps-pb-hint">No activity yet · saves, publishes and comments will show up here.</p>
                    @else
                        <ul class="ps-pb-activity-list">
                            @foreach ($feed as $row)
                                @php
                                    $icon = match ($row['verb']) {
                                        'saved'            => '💾',
                                        'published'        => '🚀',
                                        'unpublished'      => '🚀',
                                        'comment_added',
                                        'comment_resolved' => '💬',
                                        'lock_acquired'    => '🔒',
                                        default            => '·',
                                    };
                                @endphp
                                <li class="ps-pb-activity-row">
                                    <span class="ps-pb-activity-icon">{{ $icon }}</span>
                                    <span class="ps-pb-activity-text">{{ $row['summary'] }}</span>
                                    @if ($row['created_at'])
                                        <span class="ps-pb-activity-when"
                                              :title="new Date('{{ $row['created_at'] }}').toLocaleString()">
                                            {{ \Carbon\Carbon::parse($row['created_at'])->diffForHumans() }}
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="ps-pb-section" x-show="rightTab === 'settings'" x-cloak>
                    <h3>Settings</h3>
                    @if ($this->selectedBlock)
                        @php $block = $this->selectedBlock; $prefix = $this->selectedSettingsPrefix(); @endphp
                        <p class="ps-pb-hint">Editing <code>{{ $block['type'] }}</code></p>
                        @foreach ($this->selectedBlockSchema as $key => $def)
                            <div class="ps-pb-field" data-field-key="{{ $key }}">
                                <div class="ps-pb-field-head">
                                    <label>{{ $def['label'] ?? $key }}</label>
                                    @if (! empty($this->variables) && in_array($def['kind'], ['text','textarea','url'], true))
                                        <button type="button"
                                                class="ps-pb-var-btn"
                                                @pointerdown="rememberCaret($event, @js($prefix.$key))"
                                                @click="openVarPickerForButton($event, @js($prefix.$key))"
                                                title="Insert a route variable">
                                            { } var
                                        </button>
                                    @endif
                                </div>
                                @if ($def['kind'] === 'textarea')
                                    <textarea
                                        rows="4"
                                        data-wire-prop="{{ $prefix.$key }}"
                                        wire:model.live.debounce.300ms="{{ $prefix.$key }}"
                                        @dragover.prevent="onDropZoneOver($event)"
                                        @dragleave="$event.currentTarget.removeAttribute('data-ps-var-drop')"
                                        @drop.prevent="onDropIntoField($event)"
                                        @contextmenu.prevent="openVarPicker($event)"
                                    ></textarea>
                                @elseif ($def['kind'] === 'select')
                                    <select wire:model.live="{{ $prefix.$key }}">
                                        @foreach ($def['options'] ?? [] as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="{{ $def['kind'] === 'url' ? 'url' : ($def['kind'] === 'number' ? 'number' : 'text') }}"
                                        data-wire-prop="{{ $prefix.$key }}"
                                        wire:model.live.debounce.300ms="{{ $prefix.$key }}"
                                        @dragover.prevent="onDropZoneOver($event)"
                                        @dragleave="$event.currentTarget.removeAttribute('data-ps-var-drop')"
                                        @drop.prevent="onDropIntoField($event)"
                                        @contextmenu.prevent="openVarPicker($event)"
                                    >
                                @endif
                            </div>
                        @endforeach
                        @if (empty($this->selectedBlockSchema))
                            <p class="ps-pb-hint">This block has no editable settings.</p>
                        @endif
                    @else
                        <p class="ps-pb-hint">Click a block in the canvas to edit its settings.</p>
                    @endif
                </section>
            </aside>
        </div>
    @endif

    {{-- In-page search · Ctrl-F or '/' opens a unified palette over blocks +
         nodes. Clicking a result selects + scrolls to it. --}}
    <div x-data="pageStudioFinder()"
         x-show="open"
         x-cloak
         @keydown.window.ctrl.f.prevent="open = true; $nextTick(() => $refs.input.focus())"
         @keydown.window.meta.f.prevent="open = true; $nextTick(() => $refs.input.focus())"
         @keydown.window="if ($event.key === '/' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) { open = true; $event.preventDefault(); $nextTick(() => $refs.input.focus()); }"
         @keydown.escape.window="open = false; query = ''"
         class="ps-pb-find-wrap">
        <div class="ps-pb-find-backdrop" @click="open = false; query = ''"></div>
        <div class="ps-pb-find" role="dialog" aria-label="Find blocks and nodes">
            <input type="search"
                   x-ref="input"
                   x-model="query"
                   placeholder="Find blocks and nodes by type or text..."
                   class="ps-pb-find-input"
                   @keydown.enter.prevent="commit()"
                   @keydown.down.prevent="cursor = Math.min(cursor + 1, results.length - 1)"
                   @keydown.up.prevent="cursor = Math.max(cursor - 1, 0)">
            <div class="ps-pb-find-results">
                <template x-if="! query.trim()">
                    <p class="ps-pb-find-hint">Type to search · ↑↓ to navigate · Enter to jump · Esc to close.</p>
                </template>
                <template x-if="query.trim() && results.length === 0">
                    <p class="ps-pb-find-hint">No matches.</p>
                </template>
                <template x-for="(r, i) in results" :key="r.kind + ':' + r.id">
                    <button type="button"
                            class="ps-pb-find-row"
                            :class="cursor === i ? 'is-active' : ''"
                            @mouseenter="cursor = i"
                            @click="commit(i)">
                        <span class="ps-pb-find-kind" x-text="r.kind === 'block' ? 'BLOCK' : 'NODE'"></span>
                        <span class="ps-pb-find-icon" x-text="r.icon || '·'"></span>
                        <span class="ps-pb-find-label" x-text="r.label"></span>
                        <span class="ps-pb-find-preview" x-text="r.preview"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Block-tree search and replace · Ctrl-H opens a modal that fires
         searchAndReplace() across every string-valued block setting. --}}
    <div x-data="pageStudioReplacer()"
         x-show="open"
         x-cloak
         @keydown.window.ctrl.h.prevent="open = true; $nextTick(() => $refs.find.focus())"
         @keydown.window.meta.h.prevent="open = true; $nextTick(() => $refs.find.focus())"
         @keydown.escape.window="open = false"
         class="ps-pb-find-wrap">
        <div class="ps-pb-find-backdrop" @click="open = false"></div>
        <div class="ps-pb-find" role="dialog" aria-label="Search and replace">
            <div class="ps-pb-replace-row">
                <label class="ps-pb-replace-field">
                    <span>Find</span>
                    <input type="text"
                           x-ref="find"
                           x-model="find"
                           placeholder="Text to find..."
                           @keydown.enter.prevent="run()">
                </label>
                <label class="ps-pb-replace-field">
                    <span>Replace with</span>
                    <input type="text"
                           x-model="replace"
                           placeholder="Replacement..."
                           @keydown.enter.prevent="run()">
                </label>
            </div>
            <div class="ps-pb-replace-controls">
                <label class="ps-pb-checkbox">
                    <input type="checkbox" x-model="regex">
                    <span>Regular expression (use full <code>/pattern/flags</code>)</span>
                </label>
                <div class="ps-pb-replace-actions">
                    <button type="button"
                            class="ps-pb-btn"
                            @click="open = false">Cancel</button>
                    <button type="button"
                            class="ps-pb-btn ps-pb-btn--primary"
                            :disabled="busy || ! find"
                            @click="run()">Replace all</button>
                </div>
            </div>
            <p class="ps-pb-find-hint" x-show="! find">
                Ctrl-H · type a string to find, leave Replace blank to delete matches.
            </p>
        </div>
    </div>

    {{-- Hot-key cheat sheet · ? opens, Esc / outside-click closes --}}
    <div x-data="{ open: false }"
         x-show="open"
         x-cloak
         @keydown.window="if ($event.key === '?' && !['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) open = true"
         @keydown.escape.window="open = false"
         class="ps-pb-cheats-wrap">
        <div class="ps-pb-cheats-backdrop" @click="open = false"></div>
        <div class="ps-pb-cheats">
            <h3>Keyboard shortcuts</h3>
            <table>
                <tr><th>?</th><td>This menu</td></tr>
                <tr><th>Ctrl-F · /</th><td>Find blocks + nodes</td></tr>
                <tr><th>Ctrl-H</th><td>Replace text across the page</td></tr>
                <tr><th>Right-click block</th><td>Block context menu</td></tr>
                <tr><th>Ctrl-Z / Ctrl-Shift-Z</th><td>Undo / redo</td></tr>
                <tr><th>Ctrl-D</th><td>Duplicate selected node</td></tr>
                <tr><th>Ctrl-C / Ctrl-V</th><td>Copy / paste a subgraph</td></tr>
                <tr><th>Delete / Backspace</th><td>Remove selected nodes</td></tr>
                <tr><th>Ctrl-Scroll</th><td>Zoom canvas (cursor-anchored)</td></tr>
                <tr><th>Middle-mouse drag · Alt-drag</th><td>Pan canvas</td></tr>
                <tr><th>Drag socket → socket</th><td>Connect</td></tr>
                <tr><th>Drag socket → empty</th><td>Quick-add picker</td></tr>
                <tr><th>Shift-drag mid-wire</th><td>Bend wire</td></tr>
                <tr><th>Alt-click wire</th><td>Clear bend</td></tr>
                <tr><th>Right-click chip / canvas</th><td>Context menu</td></tr>
                <tr><th>Drag a number setting</th><td>Scrub value</td></tr>
            </table>
            <footer><button type="button" @click="open = false" class="ps-pb-btn">Close</button></footer>
        </div>
    </div>

    {{-- Compare-revisions overlay · two columns rendering picked revisions --}}
    @if ($compareOpen)
        @php
            $cmp = $this->compareRevisions((int) $compareAId, (int) $compareBId);
            $aPreview = $this->renderRevisionPreview($compareAId);
            $bPreview = $this->renderRevisionPreview($compareBId);
            $list = $this->revisionsList;
        @endphp
        <div class="ps-pb-compare-wrap"
             @keydown.escape.window="$wire.closeCompare()">
            <div class="ps-pb-compare-backdrop" wire:click="closeCompare"></div>
            <div class="ps-pb-compare">
                <header class="ps-pb-compare-head">
                    <h3>Compare revisions</h3>
                    <div class="ps-pb-compare-diff">
                        @if ($cmp['a'] && $cmp['b'])
                            <span>Blocks {{ $cmp['diff']['blocks'] >= 0 ? '+' : '' }}{{ $cmp['diff']['blocks'] }}</span>
                            <span>Nodes {{ $cmp['diff']['nodes'] >= 0 ? '+' : '' }}{{ $cmp['diff']['nodes'] }}</span>
                            <span>Edges {{ $cmp['diff']['edges'] >= 0 ? '+' : '' }}{{ $cmp['diff']['edges'] }}</span>
                        @else
                            <span>Pick two revisions to diff</span>
                        @endif
                    </div>
                    <button type="button" wire:click="closeCompare" class="ps-pb-btn">Close</button>
                </header>

                <div class="ps-pb-compare-pickers">
                    <label>
                        <span>Revision A</span>
                        <select wire:model.live="compareAId">
                            @foreach ($list as $r)
                                <option value="{{ $r['id'] }}">
                                    #{{ $r['id'] }} · {{ $r['created_at_iso'] ? \Carbon\Carbon::parse($r['created_at_iso'])->diffForHumans() : '?' }}
                                    · {{ $r['block_count'] }} blocks
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Revision B</span>
                        <select wire:model.live="compareBId">
                            @foreach ($list as $r)
                                <option value="{{ $r['id'] }}">
                                    #{{ $r['id'] }} · {{ $r['created_at_iso'] ? \Carbon\Carbon::parse($r['created_at_iso'])->diffForHumans() : '?' }}
                                    · {{ $r['block_count'] }} blocks
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="ps-pb-compare-cols">
                    <div class="ps-pb-compare-col">
                        <div class="ps-pb-compare-col-head">A · #{{ $compareAId }}</div>
                        <div class="ps-pb-compare-col-body">
                            @if ($aPreview === '')
                                <p class="ps-pb-empty">No content for this revision.</p>
                            @else
                                {!! $aPreview !!}
                            @endif
                        </div>
                    </div>
                    <div class="ps-pb-compare-col">
                        <div class="ps-pb-compare-col-head">B · #{{ $compareBId }}</div>
                        <div class="ps-pb-compare-col-body">
                            @if ($bPreview === '')
                                <p class="ps-pb-empty">No content for this revision.</p>
                            @else
                                {!! $bPreview !!}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ─── Bottom drawer · node variable editor ───────────────────────────── --}}
    @if ($drawerOpen && ! $previewMode)
        <section class="ps-ne-drawer"
                 data-component="page-studio.node-editor"
                 x-data="{ height: parseInt(localStorage.getItem('psPbDrawerH') || '352') }"
                 x-init="$watch('height', v => localStorage.setItem('psPbDrawerH', String(v)))"
                 :style="`height:${height}px`">
            {{-- Grabber · drag up/down to resize the drawer. --}}
            <div class="ps-ne-grabber"
                 @pointerdown="
                    const start = $event.clientY;
                    const origin = height;
                    const move = ev => { height = Math.max(120, Math.min(window.innerHeight - 80, origin + (start - ev.clientY))); };
                    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
                    window.addEventListener('pointermove', move);
                    window.addEventListener('pointerup', up);
                    $event.preventDefault();
                 "
                 title="Drag to resize the node drawer"></div>
            <header class="ps-ne-drawer-bar">
                <span class="ps-ne-title">Nodes</span>
                @if ($pendingConnection)
                    <span class="ps-ne-pending">
                        Wiring from
                        <code>{{ $pendingConnection['node'] }}.{{ $pendingConnection['socket'] }}</code>
                        · click an input socket to connect, or click the same socket to cancel
                    </span>
                @endif
                <div class="ps-ne-drawer-actions">
                    <button type="button" wire:click="undo"
                            class="ps-pb-btn"
                            @disabled(empty($undoStack))
                            title="Undo (Ctrl-Z)">↶</button>
                    <button type="button" wire:click="redo"
                            class="ps-pb-btn"
                            @disabled(empty($redoStack))
                            title="Redo (Ctrl-Shift-Z)">↷</button>

                    {{-- Revisions dropdown · click the timestamp to restore --}}
                    <details class="ps-pb-revisions"
                             @if (empty($this->revisions)) hidden @endif>
                        <summary class="ps-pb-btn" title="Recent saves">⏱ Revisions</summary>
                        <div class="ps-pb-revisions-menu">
                            @foreach ($this->revisions as $r)
                                <button type="button"
                                        wire:click="restoreRevision({{ $r['id'] }})"
                                        class="ps-pb-revisions-item"
                                        title="Restore this snapshot">
                                    <span class="ps-pb-revisions-time">{{ \Carbon\Carbon::parse($r['created_at'])->diffForHumans() }}</span>
                                    <span class="ps-pb-revisions-meta">{{ $r['blocks'] }} blocks · {{ $r['nodes'] }} nodes</span>
                                </button>
                            @endforeach
                        </div>
                    </details>
                    <button type="button"
                            wire:click="openCompare"
                            class="ps-pb-btn"
                            @if (count($this->revisionsList) < 2) disabled @endif
                            title="Compare two revisions side-by-side">
                        ⇆ Compare
                    </button>
                    <button type="button" wire:click="tidy"
                            class="ps-pb-btn"
                            title="Auto-arrange nodes by dependency depth">
                        ⇋ Tidy
                    </button>
                    <button type="button" wire:click="saveGraph"
                            wire:target="saveGraph"
                            wire:loading.attr="disabled"
                            class="ps-pb-btn ps-pb-btn--primary">
                        <span wire:loading.remove wire:target="saveGraph">Save graph</span>
                        <span wire:loading wire:target="saveGraph">Saving…</span>
                    </button>
                    <button type="button" wire:click="toggleDrawer"
                            class="ps-pb-btn">Hide</button>
                </div>
            </header>

            <div class="ps-ne-grid">
                {{-- ─── LEFT · palette ──────────────────────────────────── --}}
                <aside class="ps-ne-palette"
                       x-data="{ query: '' }">
                    <input type="search" placeholder="Search…"
                           class="ps-ne-palette-search"
                           x-model="query"
                           @keydown.escape="query = ''">

                    @foreach ($this->nodeLibrary as $group => $types)
                        @php
                            // Match the group at PHP-build time too · empty groups
                            // shouldn't render their heading.
                            $groupKey = strtolower($group);
                        @endphp
                        <div x-show="(function(){
                                const q = query.trim().toLowerCase();
                                if (!q) return true;
                                // The group is visible if any of its items match.
                                const labels = @js(array_map(
                                    fn ($d) => strtolower($d['label'] ?? ''),
                                    array_values($types),
                                ));
                                return labels.some(l => l.includes(q));
                             })()">
                            <h3>{{ ucfirst($group) }}</h3>
                            @foreach ($types as $key => $def)
                                @php $labelLower = strtolower($def['label'] ?? ''); @endphp
                                <div class="ps-ne-palette-row"
                                     x-show="!query.trim() || @js($labelLower).includes(query.trim().toLowerCase())">
                                    <button type="button"
                                            class="ps-ne-palette-item"
                                            draggable="true"
                                            wire:click="addNode(@js($key))"
                                            @dragstart="
                                                $event.dataTransfer.setData('text/plain', 'ps-ne-palette:' + @js($key));
                                                $event.dataTransfer.effectAllowed = 'copy';
                                            "
                                            title="Drag onto the canvas or click to drop at the default spot">
                                        <span class="ps-ne-palette-icon">{{ $def['icon'] }}</span>
                                        <span>{{ $def['label'] }}</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </aside>

                {{-- ─── CENTRE · canvas + wires ─────────────────────────── --}}
                <div class="ps-ne-canvas-wrap"
                     x-data="pageStudioNodeCanvas()"
                     x-init="init()"
                     @click.self="$wire.selectNode(null); closeCtxMenu()"
                     @dragover.prevent="onCanvasDragOver($event)"
                     @drop.prevent="onCanvasDrop($event)"
                     @contextmenu.prevent="openCtxMenu($event)"
                     @wheel.prevent="onWheel($event)"
                     @pointerdown="onCanvasPointerDown($event)">

                    {{-- Floating viewport controls · zoom in/out + reset --}}
                    <div class="ps-ne-viewport-ctl">
                        <button type="button" @click="zoomBy(1.15)" title="Zoom in">+</button>
                        <button type="button" @click="zoomBy(0.85)" title="Zoom out">−</button>
                        <button type="button" @click="resetView()" title="Reset view">⟲</button>
                        <span class="ps-ne-viewport-zoom" x-text="Math.round(zoom * 100) + '%'"></span>
                    </div>

                    <div class="ps-ne-stage"
                         :style="`transform: translate(${pan.x}px, ${pan.y}px) scale(${zoom})`">
                    <svg class="ps-ne-wires" preserveAspectRatio="none" :viewBox="viewBox">
                        @php $nodeIndex = []; foreach ($nodes as $i => $n) $nodeIndex[$n['id']] = $i; @endphp
                        @foreach ($edges as $e)
                            @php
                                $fromIx = $nodeIndex[$e['from_node']] ?? null;
                                $toIx   = $nodeIndex[$e['to_node']]   ?? null;
                            @endphp
                            @if ($fromIx !== null && $toIx !== null)
                                <path
                                    class="ps-ne-wire"
                                    data-edge-id="{{ $e['id'] ?? '' }}"
                                    data-from-node="{{ $e['from_node'] }}"
                                    data-from-socket="{{ $e['from_socket'] }}"
                                    data-to-node="{{ $e['to_node'] }}"
                                    data-to-socket="{{ $e['to_socket'] }}"
                                    @if (! empty($e['bend']))
                                        data-bend-x="{{ $e['bend']['x'] }}"
                                        data-bend-y="{{ $e['bend']['y'] }}"
                                    @endif
                                    @click.alt.stop="$wire.bendEdge(@js($e['id'] ?? ''), null, null)"
                                    @click="$wire.disconnectInput(@js($e['to_node']), @js($e['to_socket']))"
                                    @pointerdown.shift.stop="startWireBend($event, @js($e['id'] ?? ''))"
                                ></path>
                            @endif
                        @endforeach
                        {{-- Ghost wire · followed during drag-to-connect. --}}
                        <path id="ps-ne-ghost-wire" class="ps-ne-wire ps-ne-wire--ghost"
                              data-wire-type="any"></path>
                    </svg>

                    @if (empty($nodes))
                        <div class="ps-ne-empty">
                            <div class="ps-ne-empty-glyph">⌘</div>
                            <p>Empty graph</p>
                            <p class="ps-ne-empty-hint">Click a node type on the left to drop it onto this canvas.<br>
                                Wire <strong>output sockets</strong> (right side of a node) to <strong>input sockets</strong> by clicking one then the other.</p>
                        </div>
                    @endif

                    @foreach ($nodes as $i => $node)
                        @php
                            $schema = (config('page-studio.nodes', []))[$node['type']] ?? [];
                            $isNote = ($schema['group'] ?? '') === 'note';
                            $liveOutputs = ($this->nodeSocketValues)[$node['id']] ?? [];
                        @endphp
                        <div
                            class="ps-ne-node{{ $isNote ? ' ps-ne-node--note' : '' }} ps-ne-node--group-{{ $schema['group'] ?? 'source' }}{{ ! empty($node['muted']) ? ' is-muted' : '' }}"
                            :class="{
                                'is-selected':       $wire.selectedNodeId === @js($node['id']),
                                'is-multi-selected': selectedIds.has(@js($node['id'])),
                            }"
                            data-node-id="{{ $node['id'] }}"
                            data-node-group="{{ $schema['group'] ?? 'source' }}"
                            style="transform: translate({{ $node['position']['x'] ?? 0 }}px, {{ $node['position']['y'] ?? 0 }}px)"
                            wire:key="node-{{ $node['id'] }}"
                            @click.stop="$wire.selectNode(@js($node['id']))"
                            @pointerdown.self="startNodeDrag($event, @js($node['id']))"
                        >
                            <header class="ps-ne-node-header"
                                    @pointerdown="startNodeDrag($event, @js($node['id']))">
                                <span class="ps-ne-node-icon">{{ $schema['icon'] ?? '?' }}</span>
                                <span class="ps-ne-node-label">{{ $schema['label'] ?? $node['type'] }}</span>
                                @if (isset(($this->nodeOrder)[$node['id']]))
                                    <span class="ps-ne-node-order"
                                          title="Evaluation order">{{ ($this->nodeOrder)[$node['id']] + 1 }}</span>
                                @endif
                                @if (! $isNote)
                                    <button type="button"
                                            class="ps-ne-node-action{{ ! empty($node['muted']) ? ' is-on' : '' }}"
                                            wire:click.stop="toggleMuted(@js($node['id']))"
                                            aria-label="Mute node"
                                            aria-pressed="{{ ! empty($node['muted']) ? 'true' : 'false' }}"
                                            title="Mute node · input passes straight through to output">M</button>
                                @endif
                                @if (in_array($node['type'] ?? '', ['source.model_finder', 'source.auth_user'], true))
                                    <button type="button"
                                            class="ps-ne-node-action{{ ! empty($node['settings']['expose_fields']) ? ' is-on' : '' }}"
                                            wire:click.stop="toggleModelFields(@js($node['id']))"
                                            aria-label="Expose fields as outputs"
                                            aria-pressed="{{ ! empty($node['settings']['expose_fields']) ? 'true' : 'false' }}"
                                            title="Toggle one-socket-per-field vs single model output">⚏</button>
                                @endif
                                <button type="button" class="ps-ne-node-action"
                                        wire:click.stop="duplicateNode(@js($node['id']))"
                                        aria-label="Duplicate node"
                                        title="Duplicate (Ctrl-D)">⎘</button>
                                <button type="button" class="ps-ne-node-remove"
                                        wire:click.stop="removeNode(@js($node['id']))"
                                        aria-label="Delete node"
                                        title="Delete">✕</button>
                            </header>

                            @if ($isNote)
                                {{-- Sticky note · raw text, no sockets. --}}
                                <div class="ps-ne-node-note">{{ $node['settings']['text'] ?? '' }}</div>
                            @else
                                <div class="ps-ne-node-body">
                                    {{-- Inputs · click to complete a pending connection --}}
                                    @foreach ($schema['inputs'] ?? [] as $key => $entry)
                                        @php $sock = \LoggedCloud\PageStudio\Support\NodeSchema::normaliseSocket($entry); @endphp
                                        <div class="ps-ne-socket-row ps-ne-socket-row--in">
                                            <button type="button"
                                                    class="ps-ne-socket ps-ne-socket--in ps-ne-socket--type-{{ $sock['type'] }}"
                                                    data-socket-node="{{ $node['id'] }}"
                                                    data-socket-key="{{ $key }}"
                                                    data-socket-kind="in"
                                                    data-socket-type="{{ $sock['type'] }}"
                                                    wire:click.stop="completeConnection(@js($node['id']), @js($key))"
                                                    title="{{ $sock['label'] }} · type: {{ $sock['type'] }}"></button>
                                            <span class="ps-ne-socket-label">{{ $sock['label'] }}</span>
                                            <span class="ps-ne-type-pill ps-ne-type-pill--{{ $sock['type'] }}">{{ $sock['type'] }}</span>
                                        </div>
                                    @endforeach

                                    {{-- Outputs · click to start a connection. Live value shows below the row. --}}
                                    @foreach ($this->outputsFor($node) as $key => $entry)
                                        @php
                                            $sock = \LoggedCloud\PageStudio\Support\NodeSchema::normaliseSocket($entry);
                                            $isPending = $pendingConnection
                                                && $pendingConnection['node'] === $node['id']
                                                && $pendingConnection['socket'] === $key;
                                            $liveValue = $liveOutputs[$key] ?? null;
                                            $livePreview = is_scalar($liveValue) || $liveValue === null
                                                ? (string) $liveValue
                                                : (is_object($liveValue)
                                                    ? '['.class_basename($liveValue).']'
                                                    : json_encode($liveValue));
                                            // Trim very long previews so a node
                                            // doesn't blow out the canvas.
                                            if (mb_strlen($livePreview) > 28) $livePreview = mb_substr($livePreview, 0, 26).'…';
                                        @endphp
                                        <div class="ps-ne-socket-row ps-ne-socket-row--out">
                                            <span class="ps-ne-type-pill ps-ne-type-pill--{{ $sock['type'] }}">{{ $sock['type'] }}</span>
                                            <span class="ps-ne-socket-label">{{ $sock['label'] }}</span>
                                            <button type="button"
                                                    class="ps-ne-socket ps-ne-socket--out ps-ne-socket--type-{{ $sock['type'] }}{{ $isPending ? ' is-pending' : '' }}"
                                                    data-socket-node="{{ $node['id'] }}"
                                                    data-socket-key="{{ $key }}"
                                                    data-socket-kind="out"
                                                    data-socket-type="{{ $sock['type'] }}"
                                                    wire:click.stop="startConnection(@js($node['id']), @js($key))"
                                                    @pointerdown="startSocketDrag($event, @js($node['id']), @js($key), @js($sock['type']))"
                                                    title="Drag to an input socket, or click then click an input"></button>
                                        </div>
                                        @if ($sock['type'] === 'image' && is_array($liveValue) && ! empty($liveValue['url']))
                                            {{-- Live image thumbnail with the
                                                 current CSS-filter chain · the
                                                 Blender-style WYSIWYG bit. --}}
                                            <div class="ps-ne-image-preview"
                                                 title="filter: {{ $liveValue['filter'] ?: 'none' }}">
                                                <img src="{{ $liveValue['url'] }}"
                                                     alt=""
                                                     style="filter: {{ $liveValue['filter'] ?: 'none' }}"
                                                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                                                <span class="ps-ne-image-preview-fail">image failed to load</span>
                                                @if (! empty($liveValue['filter']))
                                                    <code class="ps-ne-image-filter">{{ $liveValue['filter'] }}</code>
                                                @endif
                                            </div>
                                        @elseif ($livePreview !== '')
                                            <div class="ps-ne-live-value"
                                                 title="Current value with sample variable data: {{ $livePreview }}">
                                                = {{ $livePreview }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                    </div>{{-- /.ps-ne-stage --}}

                    {{-- Marquee selection rectangle · viewport-local. --}}
                    <div class="ps-ne-marquee"
                         x-show="marquee.active"
                         x-cloak
                         :style="`left:${marquee.x}px;top:${marquee.y}px;width:${marquee.w}px;height:${marquee.h}px`">
                    </div>

                    {{-- Mini-map · whole graph at a glance, click to recentre. --}}
                    <div class="ps-ne-minimap"
                         x-show="@js(! empty($nodes))"
                         @click="recentreOn($event)">
                        <svg viewBox="0 0 100 60" preserveAspectRatio="none">
                            @foreach ($nodes as $node)
                                @php
                                    $schema = (config('page-studio.nodes', []))[$node['type']] ?? [];
                                    $g = $schema['group'] ?? 'source';
                                    $fill = match ($g) {
                                        'transform' => '#3b82f6',
                                        'image'     => '#14b8a6',
                                        'output'    => '#22c55e',
                                        'note'      => '#fde68a',
                                        default     => '#f43f5e',
                                    };
                                @endphp
                                {{-- Stage span is 4000x2400 · scale into 100x60. --}}
                                <rect fill="{{ $fill }}" opacity="0.85"
                                      width="3" height="2"
                                      x="{{ (($node['position']['x'] ?? 0) / 4000) * 100 }}"
                                      y="{{ (($node['position']['y'] ?? 0) / 2400) * 60 }}"></rect>
                            @endforeach
                            {{-- Viewport rectangle · computed in JS. --}}
                            <rect class="ps-ne-minimap-viewport"
                                  fill="none" stroke="#fff" stroke-width=".4" opacity=".7"
                                  :x="(-pan.x / zoom / 4000) * 100"
                                  :y="(-pan.y / zoom / 2400) * 60"
                                  :width="(($root.clientWidth || 800) / zoom / 4000) * 100"
                                  :height="(($root.clientHeight || 400) / zoom / 2400) * 60">
                            </rect>
                        </svg>
                    </div>

                    {{-- ─── Right-click context menu on the canvas ──────────── --}}
                    <div
                        x-show="ctxMenu.open"
                        x-cloak
                        :style="`top:${ctxMenu.y}px;left:${ctxMenu.x}px`"
                        @click.outside="closeCtxMenu()"
                        @keydown.escape.window="closeCtxMenu()"
                        class="ps-ne-ctx-menu"
                        role="menu"
                    >
                        @if (! empty($this->variables))
                            <div class="ps-ne-ctx-section">Insert variable</div>
                            @foreach ($this->variables as $v)
                                <button type="button" class="ps-ne-ctx-item"
                                        @click.stop="dropVarHere(@js($v['name']))"
                                        role="menuitem">
                                    <code>&#123;&#123; {{ $v['name'] }} &#125;&#125;</code>
                                    <span class="ps-ne-ctx-preview">{{ $v['preview'] }}</span>
                                </button>
                            @endforeach
                        @endif

                        @foreach ($this->nodeLibrary as $group => $types)
                            <div class="ps-ne-ctx-section">{{ ucfirst($group) }}</div>
                            @foreach ($types as $key => $def)
                                <button type="button" class="ps-ne-ctx-item"
                                        @click.stop="dropNodeHere(@js($key))"
                                        role="menuitem">
                                    <span class="ps-ne-ctx-icon">{{ $def['icon'] }}</span>
                                    <span>{{ $def['label'] }}</span>
                                </button>
                            @endforeach
                        @endforeach
                    </div>

                    {{-- ─── Connect-then-add picker ─────────────────────────── --}}
                    <div
                        x-show="quickAdd.open"
                        x-cloak
                        :style="`top:${quickAdd.y}px;left:${quickAdd.x}px`"
                        @click.outside="closeQuickAdd()"
                        @keydown.escape.window="closeQuickAdd()"
                        class="ps-ne-ctx-menu ps-ne-quick-add"
                        role="menu"
                    >
                        <div class="ps-ne-ctx-section"
                             x-text="`Insert node accepting ${quickAdd.fromType}`"></div>
                        @php
                            $allNodes = config('page-studio.nodes', []);
                            $disabledNodes = array_flip((array) config('page-studio.disabled_nodes', []));
                            $allNodes = array_diff_key($allNodes, $disabledNodes);
                        @endphp
                        @foreach ($allNodes as $key => $def)
                            @php
                                $inputs = $def['inputs'] ?? [];
                                if (empty($inputs)) continue;
                                $firstInputType = ((array) (reset($inputs) ?: []))['type'] ?? 'any';
                            @endphp
                            <button type="button" class="ps-ne-ctx-item"
                                    @click.stop="quickAddNode(@js($key))"
                                    x-show="
                                        quickAdd.fromType === 'any'
                                        || @js($firstInputType) === 'any'
                                        || quickAdd.fromType === @js($firstInputType)
                                    "
                                    role="menuitem">
                                <span class="ps-ne-ctx-icon">{{ $def['icon'] ?? '?' }}</span>
                                <span>{{ $def['label'] ?? $key }}</span>
                                <span class="ps-ne-ctx-preview ps-ne-type-pill ps-ne-type-pill--{{ $firstInputType }}">{{ $firstInputType }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- ─── RIGHT · node settings · only rendered while a node is selected ─── --}}
                @if ($this->selectedNode)
                <aside class="ps-ne-settings">
                    <h3>Node settings</h3>
                    @if (true)
                        @php $node = $this->selectedNode; $schema = $this->selectedNodeSchema; $prefix = $this->selectedNodeSettingsPrefix(); @endphp
                        <p class="ps-pb-hint">Editing <code>{{ $node['type'] }}</code></p>
                        @if (empty($schema['settings']))
                            <p class="ps-pb-hint">No editable settings.</p>
                        @else
                            @foreach ($schema['settings'] as $key => $def)
                                <div class="ps-pb-field">
                                    <label>
                                        {{ $def['label'] ?? $key }}
                                        @if (($def['kind'] ?? 'text') === 'number')
                                            <span class="ps-pb-hint" style="text-transform:none">· drag to scrub</span>
                                        @endif
                                    </label>
                                    @if (($def['kind'] ?? 'text') === 'select')
                                        <select wire:model.live="{{ $prefix.$key }}">
                                            @foreach ($def['options'] ?? [] as $val => $lbl)
                                                <option value="{{ $val }}">{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    @elseif (($def['kind'] ?? 'text') === 'number')
                                        <input type="number" step="any"
                                               wire:model.live.debounce.300ms="{{ $prefix.$key }}">
                                    @elseif (($def['kind'] ?? 'text') === 'bool')
                                        <label class="ps-pb-checkbox">
                                            <input type="checkbox" wire:model.live="{{ $prefix.$key }}">
                                            <span>{{ $def['help'] ?? 'Enable' }}</span>
                                        </label>
                                    @elseif (($def['kind'] ?? 'text') === 'upload')
                                        @php $val = data_get($this, $prefix.$key); @endphp
                                        @if (! empty($val))
                                            <div class="ps-pb-upload-preview">
                                                <img src="{{ $val }}" alt="upload preview">
                                                <button type="button"
                                                        class="ps-pb-btn"
                                                        wire:click="clearUpload(@js($prefix.$key))">Replace</button>
                                            </div>
                                        @else
                                            <input type="file" accept="image/*"
                                                   @click="$wire.set('uploadTargetProp', @js($prefix.$key))"
                                                   wire:model="uploadFile">
                                            <div wire:loading wire:target="uploadFile"
                                                 class="ps-pb-hint" style="text-transform:none">Uploading…</div>
                                            <p class="ps-pb-hint" style="text-transform:none">Stored on the configured Laravel disk; the node emits the public URL.</p>
                                        @endif
                                    @else
                                        <input type="text"
                                               wire:model.live.debounce.300ms="{{ $prefix.$key }}">
                                    @endif
                                </div>
                            @endforeach
                        @endif
                    @endif
                </aside>
                @endif
            </div>
        </section>
    @endif

    {{-- ─── Variable picker · right-click on any text field opens this ──── --}}
    <div
        x-show="varPicker.open"
        x-cloak
        @click.outside="closeVarPicker()"
        @keydown.escape.window="closeVarPicker()"
        :style="`top:${varPicker.y}px;left:${varPicker.x}px`"
        class="ps-pb-var-picker"
        role="menu"
    >
        <div class="ps-pb-var-picker-header">Insert variable</div>
        @if (empty($this->variables))
            <div class="ps-pb-var-picker-empty">No variables on this route yet.</div>
        @else
            @foreach ($this->variables as $v)
                <button type="button" class="ps-pb-var-picker-item"
                        @click="insertVar(@js($v['name']))"
                        role="menuitem">
                    <code>&#123;&#123; {{ $v['name'] }} &#125;&#125;</code>
                    <span class="ps-pb-var-picker-preview">{{ $v['preview'] }}</span>
                </button>
            @endforeach
        @endif
    </div>

    {{-- Right-click context menu on a block wrap · Duplicate, Move into,
         Remove. Mirrors the node-canvas ctxMenu pattern but anchored to
         the block tree. --}}
    <div x-show="blockCtx.open"
         x-cloak
         :style="`top:${blockCtx.y}px;left:${blockCtx.x}px`"
         @click.outside="closeBlockCtxMenu()"
         @keydown.escape.window="closeBlockCtxMenu()"
         class="ps-pb-block-ctx-menu"
         role="menu">
        <button type="button" class="ps-pb-block-ctx-item"
                @click.stop="duplicateHere()"
                role="menuitem">
            <span class="ps-pb-block-ctx-icon">⎘</span>
            <span>Duplicate</span>
        </button>

        @php $layoutTargets = $this->layoutTargets; @endphp
        @if (! empty($layoutTargets))
            <div class="ps-pb-block-ctx-section">Move into</div>
            @foreach ($layoutTargets as $t)
                <button type="button" class="ps-pb-block-ctx-item"
                        @click.stop="moveInto(@js($t['path']), @js($t['slot']))"
                        role="menuitem"
                        x-show="blockCtx.path !== @js($t['path']) && ! (blockCtx.path || '').startsWith(@js($t['path'] . '/'))">
                    <span class="ps-pb-block-ctx-icon">▢</span>
                    <span>{{ $t['label'] }}</span>
                </button>
            @endforeach
        @endif

        <div class="ps-pb-block-ctx-section">Library</div>
        <button type="button" class="ps-pb-block-ctx-item"
                @click.stop="openSnippetPrompt()"
                role="menuitem">
            <span class="ps-pb-block-ctx-icon">★</span>
            <span>Save as snippet...</span>
        </button>

        <div class="ps-pb-block-ctx-section">Danger</div>
        <button type="button" class="ps-pb-block-ctx-item ps-pb-block-ctx-danger"
                @click.stop="removeHere()"
                role="menuitem">
            <span class="ps-pb-block-ctx-icon">✕</span>
            <span>Remove</span>
        </button>
    </div>

    {{-- Save-as-snippet prompt · tiny modal anchored over the menu --}}
    <div x-show="snippetPrompt.open"
         x-cloak
         class="ps-pb-find-wrap"
         @keydown.escape.window="snippetPrompt.open = false">
        <div class="ps-pb-find-backdrop" @click="snippetPrompt.open = false"></div>
        <div class="ps-pb-find ps-pb-snippet-prompt" role="dialog" aria-label="Save as snippet">
            <h3>Save as snippet</h3>
            <label class="ps-pb-replace-field">
                <span>Name (unique handle)</span>
                <input type="text"
                       x-ref="snippetName"
                       x-model="snippetPrompt.name"
                       placeholder="hero-callout"
                       @keydown.enter.prevent="commitSnippet()">
            </label>
            <label class="ps-pb-replace-field">
                <span>Label (shown in the palette)</span>
                <input type="text"
                       x-model="snippetPrompt.label"
                       placeholder="Hero callout"
                       @keydown.enter.prevent="commitSnippet()">
            </label>
            <div class="ps-pb-replace-actions">
                <button type="button" class="ps-pb-btn" @click="snippetPrompt.open = false">Cancel</button>
                <button type="button" class="ps-pb-btn ps-pb-btn--primary"
                        :disabled="! snippetPrompt.name.trim()"
                        @click="commitSnippet()">Save snippet</button>
            </div>
        </div>
    </div>

    {{-- Snippet library overlay · rename + delete each saved snippet --}}
    <div x-show="libraryOpen"
         x-cloak
         class="ps-pb-cheats-wrap"
         @keydown.escape.window="libraryOpen = false">
        <div class="ps-pb-cheats-backdrop" @click="libraryOpen = false"></div>
        <div class="ps-pb-cheats ps-pb-library">
            <h3>Snippet library</h3>
            @php $libraryItems = $this->snippetLibrary; @endphp
            @if (empty($libraryItems))
                <p class="ps-pb-hint">No snippets yet · right-click any block and choose "Save as snippet..." to build the library.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Icon</th>
                            <th>Name</th>
                            <th>Label</th>
                            <th>Group</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($libraryItems as $s)
                            <tr wire:key="lib-{{ $s['id'] }}">
                                <td>{{ $s['icon'] }}</td>
                                <td>
                                    <input type="text"
                                           value="{{ $s['name'] }}"
                                           @change="$wire.renameSnippet({{ $s['id'] }}, $event.target.value, $event.target.closest('tr').querySelector('[data-snip-label]').value)">
                                </td>
                                <td>
                                    <input type="text"
                                           data-snip-label
                                           value="{{ $s['label'] }}"
                                           @change="$wire.renameSnippet({{ $s['id'] }}, $event.target.closest('tr').querySelector('input').value, $event.target.value)">
                                </td>
                                <td>{{ $s['group'] }}</td>
                                <td>
                                    <button type="button" class="ps-pb-btn"
                                            wire:click="dropSnippet(@js($s['name']))"
                                            @click="libraryOpen = false">Drop</button>
                                    <button type="button" class="ps-pb-btn ps-pb-block-ctx-danger"
                                            wire:click="deleteSnippet({{ $s['id'] }})"
                                            @click="$event.preventDefault()"
                                            onclick="if(! confirm('Delete this snippet?')) return false;">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <footer><button type="button" @click="libraryOpen = false" class="ps-pb-btn">Close</button></footer>
        </div>
    </div>

    {{-- Always-on toast container · fixed-positioned so it's never clipped --}}
    <div
        x-show="toast.show"
        x-cloak
        class="ps-pb-toast"
        :class="toast.ok ? 'is-ok' : 'is-err'"
        x-text="toast.message"
        x-transition.opacity.duration.250ms
    ></div>

    @once
        @push('scripts')
            <script>
                // Unified finder · Ctrl-F / '/' opens a palette that searches
                // both the block tree and the node graph by type or settings
                // text. Clicking a result selects + scrolls it into view.
                window.pageStudioFinder = function () {
                    return {
                        open: false,
                        query: '',
                        cursor: 0,

                        get results() {
                            const q = this.query.trim().toLowerCase();
                            if (! q) return [];

                            const out = [];
                            const blocks = this.$wire.blocks || [];
                            const nodes  = this.$wire.nodes  || [];

                            const walk = (list, path) => {
                                list.forEach((b, i) => {
                                    const p = path === '' ? String(i) : `${path}/${i}`;
                                    const s = b.settings || {};
                                    const haystack = (b.type + ' ' + Object.values(s).filter(v => typeof v === 'string').join(' ')).toLowerCase();
                                    if (haystack.includes(q)) {
                                        out.push({
                                            kind:    'block',
                                            id:      p,
                                            label:   b.type,
                                            icon:    '◻︎',
                                            preview: (s.text || s.label || s.title || '').toString().slice(0, 60),
                                        });
                                    }
                                    if (b.children) {
                                        for (const slot of Object.keys(b.children)) {
                                            walk(b.children[slot] || [], `${p}/${slot}`);
                                        }
                                    }
                                });
                            };
                            walk(blocks, '');

                            nodes.forEach(n => {
                                const s = n.settings || {};
                                const haystack = (n.type + ' ' + Object.values(s).filter(v => typeof v === 'string').join(' ')).toLowerCase();
                                if (haystack.includes(q)) {
                                    out.push({
                                        kind:    'node',
                                        id:      n.id,
                                        label:   n.type,
                                        icon:    '◆',
                                        preview: (s.value || s.variable_name || s.name || '').toString().slice(0, 60),
                                    });
                                }
                            });
                            return out;
                        },

                        commit(index = null) {
                            const i = index ?? this.cursor;
                            const r = this.results[i];
                            if (! r) return;
                            if (r.kind === 'block') {
                                this.$wire.selectBlock(r.id);
                            } else {
                                this.$wire.selectNode(r.id);
                                // If the drawer is closed, open it so the user can
                                // see what was selected.
                                if (! this.$wire.drawerOpen) this.$wire.toggleDrawer();
                            }
                            this.open = false;
                            this.query = '';
                            this.cursor = 0;
                        },
                    };
                };

                // Search and replace · Ctrl-H opens a modal that pipes
                // (find, replace, regex) into the server-side replacer and
                // surfaces the resulting match count as a toast.
                window.pageStudioReplacer = function () {
                    return {
                        open: false,
                        find: '',
                        replace: '',
                        regex: false,
                        busy: false,

                        async run() {
                            if (! this.find || this.busy) return;
                            this.busy = true;
                            try {
                                const count = await this.$wire.searchAndReplace(this.find, this.replace, this.regex);
                                // Bounce a toast via the global builder scope.
                                window.dispatchEvent(new CustomEvent('page-studio:replace:done', {
                                    detail: { count: Number(count) || 0 },
                                }));
                                this.open = false;
                            } finally {
                                this.busy = false;
                            }
                        },
                    };
                };

                window.pageStudioPageBuilder = function () {
                    return {
                        // Touch DnD · HTML5 DragEvent doesn't fire on touchscreens,
                        // so a parallel pointer-event path drives the same drop
                        // commit. Long-press kicks the gesture into drag mode
                        // (without it every tap would start dragging).
                        touchDrag: { active: false, kind: null, payload: null, label: '', x: 0, y: 0, timer: null, pointerId: null, target: null },

                        startTouchDrag(e, kind, payload, label) {
                            if (e.pointerType !== 'touch') return;
                            this.touchDrag.target  = e.currentTarget;
                            this.touchDrag.pointerId = e.pointerId;
                            this.touchDrag.kind    = kind;
                            this.touchDrag.payload = payload;
                            this.touchDrag.label   = label;
                            this.touchDrag.x       = e.clientX;
                            this.touchDrag.y       = e.clientY;
                            if (this.touchDrag.timer) clearTimeout(this.touchDrag.timer);
                            this.touchDrag.timer = setTimeout(() => this.activateTouchDrag(), 220);
                            // Capture so the move + up events fire on this element
                            // even when the finger leaves it.
                            try { e.currentTarget.setPointerCapture(e.pointerId); } catch (_) {}
                            window.addEventListener('pointermove', this.boundTouchMove, { passive: false });
                            window.addEventListener('pointerup',   this.boundTouchEnd);
                            window.addEventListener('pointercancel', this.boundTouchEnd);
                        },

                        activateTouchDrag() {
                            this.touchDrag.active = true;
                            this.dragKind = this.touchDrag.kind;
                            this.dragPayload = this.touchDrag.payload;
                            document.body.style.touchAction = 'none';
                            document.body.style.userSelect = 'none';
                        },

                        onTouchMove(e) {
                            if (! this.touchDrag.kind) return;
                            // Cancel the long-press if the finger leaves the start
                            // point before the threshold fires.
                            if (! this.touchDrag.active) {
                                if (Math.hypot(e.clientX - this.touchDrag.x, e.clientY - this.touchDrag.y) > 8) {
                                    if (this.touchDrag.timer) { clearTimeout(this.touchDrag.timer); this.touchDrag.timer = null; }
                                    this.cancelTouchDrag();
                                }
                                return;
                            }
                            e.preventDefault();
                            this.touchDrag.x = e.clientX;
                            this.touchDrag.y = e.clientY;

                            // Identify what's under the finger · prefer the deepest
                            // slot, then a block wrapper, then the canvas as a root drop.
                            const el = document.elementFromPoint(e.clientX, e.clientY);
                            if (! el) return;
                            const slot   = el.closest('.ps-pb-slot');
                            const block  = el.closest('.ps-pb-block-wrap');
                            const canvas = el.closest('.ps-pb-canvas');

                            if (slot) {
                                this.dropTarget = {
                                    parentPath: slot.dataset.parentPath ?? '',
                                    slot:       slot.dataset.slot,
                                    index:      parseInt(slot.dataset.kidCount ?? '0'),
                                };
                            } else if (block) {
                                const r = block.getBoundingClientRect();
                                const inTopHalf = (e.clientY - r.top) < r.height / 2;
                                const idx = parseInt(block.dataset.index ?? '0');
                                this.dropTarget = {
                                    parentPath: block.dataset.parentPath ?? '',
                                    slot:       block.dataset.slot || null,
                                    index:      inTopHalf ? idx : idx + 1,
                                };
                            } else if (canvas) {
                                this.dropTarget = { parentPath: '', slot: null, index: this.segmentCount };
                            }
                        },

                        onTouchEnd(e) {
                            if (this.touchDrag.timer) { clearTimeout(this.touchDrag.timer); this.touchDrag.timer = null; }
                            window.removeEventListener('pointermove', this.boundTouchMove);
                            window.removeEventListener('pointerup',   this.boundTouchEnd);
                            window.removeEventListener('pointercancel', this.boundTouchEnd);

                            if (! this.touchDrag.active) {
                                // Threshold never fired · let the @click handler do its
                                // tap-to-add thing. Just clear the partial state.
                                this.cancelTouchDrag();
                                return;
                            }
                            try { this.touchDrag.target?.releasePointerCapture?.(this.touchDrag.pointerId); } catch (_) {}
                            this.commitDrop(this.dropTarget);
                            this.cancelTouchDrag();
                        },

                        cancelTouchDrag() {
                            this.touchDrag = { active: false, kind: null, payload: null, label: '', x: 0, y: 0, timer: null, pointerId: null, target: null };
                            document.body.style.touchAction = '';
                            document.body.style.userSelect = '';
                        },

                        dragKind: null,
                        dragPayload: null,
                        // Where the dragged item will land · matches the
                        // (parentPath, slot, index) shape the Livewire side
                        // expects.
                        dropTarget: { parentPath: '', slot: null, index: -1 },
                        // On phones / narrow tablets default both rails to
                        // closed so the canvas owns the screen · tapping a
                        // toggle slides the rail in as a sheet (see CSS).
                        leftCollapsed:  (typeof window !== 'undefined' && window.innerWidth <= 768)
                            ? true
                            : localStorage.getItem('psPbLeftCollapsed')  === '1',
                        rightCollapsed: (typeof window !== 'undefined' && window.innerWidth <= 768)
                            ? true
                            : localStorage.getItem('psPbRightCollapsed') === '1',
                        // Variable picker · opened by right-click on a text field.
                        // Stores the target field + its caret position at click
                        // time so we know exactly where to splice the token.
                        varPicker: { open: false, x: 0, y: 0, targetEl: null, start: 0, end: 0, wireProp: null },
                        // Right-click block context menu · path is the block
                        // the user invoked the menu over.
                        blockCtx: { open: false, x: 0, y: 0, path: '' },
                        // Save-as-snippet prompt · open by the context menu,
                        // remembers the block path while the user types.
                        snippetPrompt: { open: false, path: '', name: '', label: '' },
                        libraryOpen: false,
                        // Which right-rail tab is showing · 'settings' is the
                        // historical default; 'activity' surfaces the polling
                        // collaboration feed.
                        rightTab: 'settings',
                        toast: { show: false, ok: true, message: '' },
                        toastTimer: null,

                        init() {
                            this.$watch('leftCollapsed',  (v) => localStorage.setItem('psPbLeftCollapsed',  v ? '1' : '0'));
                            this.$watch('rightCollapsed', (v) => localStorage.setItem('psPbRightCollapsed', v ? '1' : '0'));
                            // Pre-bind the touch handlers so add/remove EventListener
                            // pair up cleanly (otherwise removeEventListener has no
                            // way to match the original closure).
                            this.boundTouchMove = (e) => this.onTouchMove(e);
                            this.boundTouchEnd  = (e) => this.onTouchEnd(e);
                            // On phones close the node drawer too · it covers
                            // most of the screen and the canvas is the focus
                            // for touch users. They can re-open via Show nodes.
                            if (typeof window !== 'undefined' && window.innerWidth <= 768) {
                                if (this.$wire.drawerOpen) this.$wire.set('drawerOpen', false);
                            }

                            // ─── Collaboration heartbeat ───────────────────
                            // Every 8s · refresh whatever block locks the
                            // current author holds and bump the presence row
                            // for this tab. Both calls no-op server-side when
                            // the editor is in ephemeral mode, so we don't
                            // need to gate the interval here.
                            this.collabHeartbeat = setInterval(() => {
                                const held = this.heldBlockIds();
                                if (held.length > 0) {
                                    this.$wire.heartbeatBlockLocks(held);
                                }
                                this.$wire.heartbeatPresence();
                            }, 8000);

                            // Release the lock + presence row on tab close ·
                            // best-effort, browsers throttle async work in
                            // beforeunload but the locks expire on their own
                            // within 30s either way.
                            this.boundBeforeUnload = () => {
                                const sel = this.$wire.selectedPath;
                                if (sel) {
                                    // Resolve the block id from the path
                                    // so the server can scope the delete.
                                    const id = this.blockIdForPath(sel);
                                    if (id) {
                                        try { this.$wire.releaseBlockLock(id); } catch (_) {}
                                    }
                                }
                            };
                            window.addEventListener('beforeunload', this.boundBeforeUnload);
                        },

                        // Resolve a block path like "0/body/2" into its block
                        // id by walking the in-memory blocks array · the lock
                        // server methods accept ids, not paths.
                        blockIdForPath(path) {
                            if (! path) return null;
                            const parts = String(path).split('/');
                            let list = this.$wire.blocks || [];
                            let node = null;
                            for (let i = 0; i < parts.length; i++) {
                                const seg = parts[i];
                                if (/^\d+$/.test(seg)) {
                                    node = list[Number(seg)];
                                    if (! node) return null;
                                } else {
                                    list = (node && node.children && node.children[seg]) || [];
                                }
                            }
                            return node ? node.id || null : null;
                        },

                        // The block ids whose locks the current tab is
                        // responsible for keeping alive · today only the
                        // selected block, but the shape stays an array so a
                        // future multi-select can pass multiple ids without
                        // changing the heartbeat method's signature.
                        heldBlockIds() {
                            const sel = this.$wire.selectedPath;
                            if (! sel) return [];
                            const id = this.blockIdForPath(sel);
                            return id ? [id] : [];
                        },

                        get selectedPath() { return this.$wire.selectedPath; },
                        get segmentCount() { return (this.$wire.blocks || []).length; },

                        onPaletteDragStart(e, type) {
                            this.dragKind = 'palette';
                            this.dragPayload = type;
                            // Firefox refuses to start a drag without any data
                            // on the transfer · Chrome happily fires drop
                            // events but with an empty payload, so include the
                            // type both ways for resilience.
                            try {
                                e.dataTransfer.setData('text/plain', 'ps-pb-palette:' + type);
                                e.dataTransfer.setData('application/x-page-studio', JSON.stringify({ kind: 'palette', type }));
                                e.dataTransfer.effectAllowed = 'copy';
                            } catch (_) {}
                        },

                        onSnippetDragStart(e, name) {
                            this.dragKind = 'snippet';
                            this.dragPayload = name;
                            try {
                                e.dataTransfer.setData('text/plain', 'ps-pb-snippet:' + name);
                                e.dataTransfer.setData('application/x-page-studio', JSON.stringify({ kind: 'snippet', name }));
                                e.dataTransfer.effectAllowed = 'copy';
                            } catch (_) {}
                        },

                        onVarDragStart(e, name) {
                            this.dragKind = 'variable';
                            this.dragPayload = name;
                            e.dataTransfer.effectAllowed = 'copy';
                            e.dataTransfer.setData('text/plain', '@{{ '+name+' }}');
                            document.body.classList.add('ps-pb-dragging-var');
                        },

                        // Right-click on a variable chip drops a Route-variable
                        // source node onto the canvas, opening the drawer first
                        // if the user had it collapsed. Keeps a single gesture
                        // path from "I want this in the graph" to "node exists".
                        async insertVarAsNode(name) {
                            if (! this.$wire.drawerOpen) {
                                await this.$wire.toggleDrawer();
                            }
                            await this.$wire.addNodeForVariable(name);
                            this.showToast('Added ' + name + ' source node', true);
                        },

                        onVarDragEnd(e) {
                            document.body.classList.remove('ps-pb-dragging-var');
                            // dropEffect = 'none' means no handler accepted the drop.
                            if (e.dataTransfer && e.dataTransfer.dropEffect === 'none') {
                                this.showToast(
                                    "That field can't receive a variable. " +
                                    "Tip: right-click any text field to pick from a list.",
                                    false,
                                );
                            }
                            this.resetDrag();
                        },

                        onBlockDragStart(e, path) {
                            this.dragKind = 'block';
                            this.dragPayload = path;
                            try {
                                e.dataTransfer.setData('text/plain', 'ps-pb-block:' + path);
                                e.dataTransfer.setData('application/x-page-studio', JSON.stringify({ kind: 'block', path }));
                                e.dataTransfer.effectAllowed = 'move';
                            } catch (_) {}
                        },

                        onCanvasDragOver(e) {
                            if (this.dragKind !== 'palette' && this.dragKind !== 'block' && this.dragKind !== 'snippet') return;
                            this.dropTarget = { parentPath: '', slot: null, index: this.segmentCount };
                            e.dataTransfer.dropEffect = this.dragKind === 'block' ? 'move' : 'copy';
                        },

                        onCanvasLeave(e) {
                            if (! e.currentTarget.contains(e.relatedTarget)) {
                                this.resetDrag(false);
                            }
                        },

                        onCanvasDrop(e) {
                            this.commitDrop({ parentPath: '', slot: null, index: this.dropTarget.index });
                        },

                        onBlockDragOver(e, parentPath, slot, index) {
                            if (this.dragKind !== 'palette' && this.dragKind !== 'block' && this.dragKind !== 'snippet') return;
                            const r = e.currentTarget.getBoundingClientRect();
                            const inTopHalf = (e.clientY - r.top) < r.height / 2;
                            this.dropTarget = {
                                parentPath, slot,
                                index: inTopHalf ? index : index + 1,
                            };
                            e.dataTransfer.dropEffect = this.dragKind === 'block' ? 'move' : 'copy';
                        },

                        onBlockDrop(e, parentPath, slot, index) {
                            this.commitDrop(this.dropTarget.index >= 0 ? this.dropTarget : { parentPath, slot, index });
                        },

                        onSlotDragOver(e, parentPath, slot, kidCount) {
                            if (this.dragKind !== 'palette' && this.dragKind !== 'block' && this.dragKind !== 'snippet') return;
                            // Default position when hovering an empty slot or the
                            // empty space inside a slot · append to the end.
                            this.dropTarget = { parentPath, slot, index: kidCount };
                            e.dataTransfer.dropEffect = this.dragKind === 'block' ? 'move' : 'copy';
                        },

                        onSlotDrop(e, parentPath, slot) {
                            const t = (this.dropTarget.parentPath === parentPath && this.dropTarget.slot === slot)
                                ? this.dropTarget
                                : { parentPath, slot, index: 0 };
                            this.commitDrop(t);
                        },

                        commitDrop(target) {
                            if (this.dragKind === 'palette') {
                                this.$wire.addBlock(this.dragPayload, target.parentPath, target.slot, target.index);
                            } else if (this.dragKind === 'snippet') {
                                this.$wire.dropSnippet(this.dragPayload, target.parentPath, target.slot, target.index);
                            } else if (this.dragKind === 'block') {
                                // Don't move a block into itself or any of its descendants.
                                if (this.isPathDescendant(target.parentPath, this.dragPayload)) {
                                    this.resetDrag();
                                    return;
                                }
                                this.$wire.moveBlock(this.dragPayload, target.parentPath, target.slot, target.index);
                            }
                            this.resetDrag();
                        },

                        isPathDescendant(candidate, ancestor) {
                            // Walk-into-self check · empty path = root, can't be a descendant.
                            if (! ancestor) return false;
                            return candidate === ancestor || candidate.startsWith(ancestor + '/');
                        },

                        resetDrag(clearTarget = true) {
                            this.dragKind = null;
                            this.dragPayload = null;
                            if (clearTarget) this.dropTarget = { parentPath: '', slot: null, index: -1 };
                        },

                        onDropZoneOver(e) {
                            if (this.dragKind !== 'variable') return;
                            // Mark the field as a valid drop target so CSS can
                            // highlight it (matches the variable accent).
                            e.currentTarget.setAttribute('data-ps-var-drop', '1');
                            e.dataTransfer.dropEffect = 'copy';
                        },

                        onDropIntoField(e) {
                            const el = e.currentTarget;
                            el.removeAttribute('data-ps-var-drop');
                            if (this.dragKind !== 'variable') return;

                            const wireProp = el.getAttribute('data-wire-prop') || this.readWireProp(el);
                            if (! wireProp) {
                                this.showToast("Can't insert here", false);
                                this.resetDrag();
                                return;
                            }
                            const start = el.selectionStart ?? el.value.length;
                            const end   = el.selectionEnd   ?? el.value.length;
                            this.$wire.insertVariable(wireProp, this.dragPayload, start, end);
                            this.showToast('Inserted @{{ ' + this.dragPayload + ' }}', true);
                            this.resetDrag();
                        },

                        showDropFeedback(_el, message, ok) {
                            this.showToast(message, ok);
                        },

                        showToast(message, ok = true) {
                            this.toast = { show: true, ok, message };
                            if (this.toastTimer) clearTimeout(this.toastTimer);
                            this.toastTimer = setTimeout(() => { this.toast.show = false; }, 1600);
                        },

                        // ─── Right-click variable picker ─────────────────────────
                        openVarPicker(e) {
                            const el = e.currentTarget;
                            this.varPicker = {
                                open: true,
                                x: e.clientX,
                                y: e.clientY,
                                targetEl: el,
                                start: el.selectionStart ?? el.value.length,
                                end:   el.selectionEnd   ?? el.value.length,
                                wireProp: el.getAttribute('data-wire-prop') || this.readWireProp(el),
                            };
                        },

                        closeVarPicker() {
                            this.varPicker.open = false;
                            this.varPicker.targetEl = null;
                        },

                        insertVar(name) {
                            const p = this.varPicker;
                            if (! p.wireProp) {
                                this.showToast("Can't insert here", false);
                                this.closeVarPicker();
                                return;
                            }
                            // Delegate to the PHP component · server-side
                            // splicing is the only path that survives the
                            // wire:model debounce + re-render race.
                            this.$wire.insertVariable(p.wireProp, name, p.start, p.end);
                            this.showToast('Inserted @{{ ' + name + ' }}', true);
                            this.closeVarPicker();
                        },

                        // ─── Click-to-insert variable button ──────────────────────
                        // The button sits beside each text field. Clicking the
                        // button blurs the field, so we snapshot the caret
                        // position on pointerdown (before focus moves).
                        rememberCaret(e, wireProp) {
                            const field = e.currentTarget.closest('.ps-pb-field')?.querySelector('[data-wire-prop="'+wireProp+'"]');
                            if (! field) return;
                            this.varPicker = {
                                open: false,
                                x: 0, y: 0,
                                targetEl: field,
                                wireProp,
                                start: field.selectionStart ?? field.value.length,
                                end:   field.selectionEnd   ?? field.value.length,
                            };
                        },

                        openVarPickerForButton(e, wireProp) {
                            const r = e.currentTarget.getBoundingClientRect();
                            // Carry the caret state captured by rememberCaret().
                            this.varPicker = {
                                ...this.varPicker,
                                open: true,
                                x: r.left,
                                y: r.bottom + 4,
                                wireProp,
                            };
                        },

                        readWireProp(el) {
                            for (const a of el.attributes) {
                                if (a.name.startsWith('wire:model')) return a.value;
                            }
                            return null;
                        },

                        // ─── Right-click block context menu ─────────────────
                        openBlockCtxMenu(e, path) {
                            // Position viewport-local so it isn't clipped by any
                            // scrolling parent inside the canvas.
                            this.blockCtx = {
                                open: true,
                                x: e.clientX,
                                y: e.clientY,
                                path,
                            };
                        },

                        closeBlockCtxMenu() {
                            this.blockCtx.open = false;
                        },

                        duplicateHere() {
                            this.$wire.duplicateBlock(this.blockCtx.path);
                            this.closeBlockCtxMenu();
                        },

                        removeHere() {
                            this.$wire.removeBlock(this.blockCtx.path);
                            this.closeBlockCtxMenu();
                        },

                        moveInto(toBlockPath, toSlot) {
                            this.$wire.moveBlock(this.blockCtx.path, toBlockPath, toSlot, 0);
                            this.closeBlockCtxMenu();
                        },

                        openSnippetPrompt() {
                            this.snippetPrompt = {
                                open: true,
                                path: this.blockCtx.path,
                                name: '',
                                label: '',
                            };
                            this.closeBlockCtxMenu();
                            this.$nextTick(() => { if (this.$refs.snippetName) this.$refs.snippetName.focus(); });
                        },

                        async commitSnippet() {
                            const name  = (this.snippetPrompt.name  || '').trim();
                            const label = (this.snippetPrompt.label || '').trim();
                            const path  = this.snippetPrompt.path;
                            if (! name) return;
                            await this.$wire.saveAsSnippet(path, name, label);
                            this.snippetPrompt.open = false;
                            this.showToast('Snippet saved · ' + (label || name), true);
                        },
                    };
                };

                // ─── Node canvas ─────────────────────────────────────────────
                window.pageStudioNodeCanvas = function () {
                    return {
                        viewBox: '0 0 4000 2400',  // big enough that wires never clip even after pan/zoom
                        drag: null,
                        rafToken: 0,
                        paletteDragType: null,
                        ctxMenu: { open: false, x: 0, y: 0, canvasX: 0, canvasY: 0 },

                        // Pan + zoom state · pan in viewport pixels, zoom is a
                        // linear scale. All node positions live in stage-local
                        // coords; the .ps-ne-stage container is transformed.
                        pan: { x: 0, y: 0 },
                        zoom: 1,
                        panDrag: null,

                        // Multi-select / marquee state.
                        marquee: { active: false, x: 0, y: 0, w: 0, h: 0 },
                        selectedIds: new Set(),

                        // Connect-then-add picker · shown after the user drops
                        // a wire on empty canvas; remembers the source so the
                        // chosen node can be auto-wired.
                        quickAdd: { open: false, x: 0, y: 0, canvasX: 0, canvasY: 0, fromNode: null, fromSocket: null, fromType: 'any' },

                        /**
                         * Project a viewport (clientX/Y) point into stage-local
                         * coords · used by every "where does the new node go"
                         * code path.
                         */
                        toStage(clientX, clientY) {
                            const r = this.$root.getBoundingClientRect();
                            return {
                                x: (clientX - r.left - this.pan.x) / this.zoom,
                                y: (clientY - r.top  - this.pan.y) / this.zoom,
                            };
                        },

                        zoomBy(factor) {
                            this.setZoom(this.zoom * factor);
                        },
                        setZoom(z) {
                            this.zoom = Math.max(0.25, Math.min(2.5, z));
                            this.queueRedraw();
                        },
                        resetView() {
                            this.pan = { x: 0, y: 0 };
                            this.zoom = 1;
                            this.queueRedraw();
                        },

                        // Shift-drag a wire to set its bend point · the wire
                        // routes through the cursor so spaghetti graphs can be
                        // bent around obstacles without dropping reroute nodes.
                        startWireBend(e, edgeId) {
                            const onMove = (ev) => {
                                const s = this.toStage(ev.clientX, ev.clientY);
                                const path = this.$root.querySelector(`.ps-ne-wire[data-edge-id="${edgeId}"]`);
                                if (! path) return;
                                path.setAttribute('data-bend-x', Math.round(s.x));
                                path.setAttribute('data-bend-y', Math.round(s.y));
                                this.queueRedraw();
                            };
                            const onUp = (ev) => {
                                window.removeEventListener('pointermove', onMove);
                                window.removeEventListener('pointerup',   onUp);
                                const s = this.toStage(ev.clientX, ev.clientY);
                                this.$wire.bendEdge(edgeId, Math.round(s.x), Math.round(s.y));
                            };
                            window.addEventListener('pointermove', onMove);
                            window.addEventListener('pointerup',   onUp);
                        },

                        showCopyToast(n) {
                            // Bubble up to the page-builder Alpine scope so it
                            // shows in the same fixed-position toast as saves.
                            this.$root.dispatchEvent(new CustomEvent('page-studio:graph:copied', {
                                detail: { count: n }, bubbles: true,
                            }));
                        },

                        recentreOn(e) {
                            // Click on the mini-map · re-centre the viewport on
                            // the corresponding stage-local coords.
                            const map = e.currentTarget.getBoundingClientRect();
                            const fx = (e.clientX - map.left) / map.width;   // 0..1
                            const fy = (e.clientY - map.top)  / map.height;
                            const cx = fx * 4000;  // stage-local target
                            const cy = fy * 2400;
                            const r = this.$root.getBoundingClientRect();
                            this.pan = {
                                x: r.width  / 2 - cx * this.zoom,
                                y: r.height / 2 - cy * this.zoom,
                            };
                            this.queueRedraw();
                        },

                        onWheel(e) {
                            // Pinch / Ctrl-wheel zooms, plain wheel pans.
                            if (e.ctrlKey || e.metaKey) {
                                const factor = e.deltaY < 0 ? 1.1 : 0.9;
                                // Zoom anchored at the cursor: re-position pan so the
                                // point under the cursor stays put.
                                const r = this.$root.getBoundingClientRect();
                                const cx = e.clientX - r.left;
                                const cy = e.clientY - r.top;
                                const newZoom = Math.max(0.25, Math.min(2.5, this.zoom * factor));
                                this.pan.x = cx - ((cx - this.pan.x) * (newZoom / this.zoom));
                                this.pan.y = cy - ((cy - this.pan.y) * (newZoom / this.zoom));
                                this.zoom = newZoom;
                            } else {
                                this.pan.x -= e.deltaX;
                                this.pan.y -= e.deltaY;
                            }
                            this.queueRedraw();
                        },

                        onCanvasPointerDown(e) {
                            // Middle-mouse drag (or Alt + left) pans the stage.
                            if (e.button === 1 || (e.button === 0 && e.altKey)) {
                                e.preventDefault();
                                this.panDrag = {
                                    startX: e.clientX, startY: e.clientY,
                                    originX: this.pan.x, originY: this.pan.y,
                                };
                                const onMove = (ev) => {
                                    this.pan = {
                                        x: this.panDrag.originX + (ev.clientX - this.panDrag.startX),
                                        y: this.panDrag.originY + (ev.clientY - this.panDrag.startY),
                                    };
                                    this.queueRedraw();
                                };
                                const onUp = () => {
                                    window.removeEventListener('pointermove', onMove);
                                    window.removeEventListener('pointerup',   onUp);
                                    this.panDrag = null;
                                };
                                window.addEventListener('pointermove', onMove);
                                window.addEventListener('pointerup',   onUp);
                                return;
                            }
                            // Left-button drag on canvas background = marquee
                            // selection. Skip when started over a node/socket
                            // or a viewport control · those have their own
                            // pointerdown handlers.
                            if (e.button !== 0) return;
                            if (e.target.closest('.ps-ne-node, .ps-ne-socket, .ps-ne-viewport-ctl, .ps-ne-wire')) return;

                            const r = this.$root.getBoundingClientRect();
                            const startX = e.clientX - r.left;
                            const startY = e.clientY - r.top;
                            this.marquee = { active: true, x: startX, y: startY, w: 0, h: 0 };
                            const onMove = (ev) => {
                                const cx = ev.clientX - r.left;
                                const cy = ev.clientY - r.top;
                                this.marquee = {
                                    active: true,
                                    x: Math.min(startX, cx),
                                    y: Math.min(startY, cy),
                                    w: Math.abs(cx - startX),
                                    h: Math.abs(cy - startY),
                                };
                            };
                            const onUp = (ev) => {
                                window.removeEventListener('pointermove', onMove);
                                window.removeEventListener('pointerup',   onUp);
                                this.commitMarquee(ev.shiftKey || ev.ctrlKey || ev.metaKey);
                                this.marquee = { active: false, x: 0, y: 0, w: 0, h: 0 };
                            };
                            window.addEventListener('pointermove', onMove);
                            window.addEventListener('pointerup',   onUp);
                        },

                        commitMarquee(additive) {
                            const m = this.marquee;
                            // Tiny drags (clicks) don't make a real marquee · clear instead.
                            if (m.w < 4 && m.h < 4) {
                                if (! additive) this.selectedIds = new Set();
                                this.$root.dispatchEvent(new CustomEvent('ps:select-set', { detail: [] }));
                                return;
                            }
                            const r = this.$root.getBoundingClientRect();
                            const next = additive ? new Set(this.selectedIds) : new Set();
                            this.$root.querySelectorAll('.ps-ne-node').forEach((el) => {
                                const b = el.getBoundingClientRect();
                                const x = b.left - r.left;
                                const y = b.top  - r.top;
                                // Hit when the node's box overlaps the marquee.
                                if (x + b.width  > m.x        && y + b.height > m.y
                                 && x            < m.x + m.w  && y            < m.y + m.h) {
                                    next.add(el.dataset.nodeId);
                                }
                            });
                            this.selectedIds = next;
                        },

                        openCtxMenu(e) {
                            const r = this.$root.getBoundingClientRect();
                            const s = this.toStage(e.clientX, e.clientY);
                            this.ctxMenu = {
                                open: true,
                                // Menu sits over the canvas (viewport-local) so it
                                // doesn't scale with the stage.
                                x: Math.round(e.clientX - r.left),
                                y: Math.round(e.clientY - r.top),
                                // Drop target stays in stage coords so pan/zoom
                                // doesn't displace the new node.
                                canvasX: Math.max(0, Math.round(s.x - 60)),
                                canvasY: Math.max(0, Math.round(s.y - 12)),
                            };
                        },
                        closeCtxMenu() { this.ctxMenu.open = false; },

                        dropVarHere(name) {
                            const { canvasX, canvasY } = this.ctxMenu;
                            this.$wire.addNodeForVariable(name, canvasX, canvasY);
                            this.closeCtxMenu();
                        },

                        dropNodeHere(type) {
                            const { canvasX, canvasY } = this.ctxMenu;
                            this.$wire.addNode(type, canvasX, canvasY);
                            this.closeCtxMenu();
                        },

                        closeQuickAdd() { this.quickAdd.open = false; },

                        async quickAddNode(type) {
                            const { canvasX, canvasY, fromNode, fromSocket } = this.quickAdd;
                            // Look up the picked node type's first input socket
                            // so we know what to wire the dragged output into.
                            const lib = @js(config('page-studio.nodes', []));
                            const schema = lib[type] || {};
                            const inputs = Object.keys(schema.inputs || {});
                            this.closeQuickAdd();

                            // Spawn the node, then connect the source's output
                            // socket to the new node's first input · the engine
                            // tolerates loose typing, the wire UI flags it.
                            await this.$wire.addNode(type, canvasX, canvasY);
                            if (inputs.length) {
                                await this.$wire.startConnection(fromNode, fromSocket);
                                // Newest node is appended to the array; its id
                                // is whatever Livewire just generated.
                                const newId = (this.$wire.nodes || []).at(-1)?.id;
                                if (newId) {
                                    await this.$wire.completeConnection(newId, inputs[0]);
                                }
                            }
                        },

                        onPaletteDragStart(e, type) {
                            this.paletteDragType = type;
                            try {
                                // Required for the drag to be allowed in Firefox.
                                e.dataTransfer.setData('text/plain', type);
                                e.dataTransfer.effectAllowed = 'copy';
                            } catch (_) {}
                        },

                        onCanvasDragOver(e) {
                            // Accept palette drags AND variable-chip drags from
                            // the page-builder's left rail · the dataTransfer
                            // text carries the chip text we need to recognise.
                            const t = e.dataTransfer?.types ?? [];
                            if (! this.paletteDragType && ! t.includes('text/plain')) return;
                            e.dataTransfer.dropEffect = 'copy';
                        },

                        onCanvasDrop(e) {
                            // Compute the drop target in STAGE-local coords so
                            // pan/zoom doesn't mis-place the new node.
                            const s = this.toStage(e.clientX, e.clientY);
                            const x = Math.max(0, Math.round(s.x - 60));
                            const y = Math.max(0, Math.round(s.y - 12));

                            const text = e.dataTransfer ? e.dataTransfer.getData('text/plain') : '';

                            // Node palette · dataTransfer carries the type with a
                            // `ps-ne-palette:` prefix so we can recognise it across
                            // sibling Alpine scopes (the palette aside has its own
                            // x-data and can't talk to this canvas's state).
                            if (text.startsWith('ps-ne-palette:')) {
                                const type = text.slice('ps-ne-palette:'.length);
                                this.$wire.addNode(type, x, y);
                                this.paletteDragType = null;
                                return;
                            }

                            // Legacy in-scope drag (kept for any caller that still
                            // sets paletteDragType directly).
                            if (this.paletteDragType) {
                                this.$wire.addNode(this.paletteDragType, x, y);
                                this.paletteDragType = null;
                                return;
                            }

                            // Variable chip from the left rail · drag payload is
                            // the chip text (curly-brace wrapped var name).
                            const m = text && text.match(new RegExp('^\\s*\\{\\{\\s*([A-Za-z_][A-Za-z0-9_]*)\\s*\\}\\}\\s*$'));
                            if (m) {
                                this.$wire.addNodeForVariable(m[1], x, y);
                            }
                        },

                        init() {
                            this.fitViewBox();
                            window.addEventListener('resize', () => this.fitViewBox());
                            this.$nextTick(() => this.redrawWires());
                            this.$watch(() => this.$wire.nodes,        () => { this.queueRedraw(); this.queueAutosave(); });
                            this.$watch(() => this.$wire.edges,        () => { this.queueRedraw(); this.queueAutosave(); });
                            this.$watch(() => this.$wire.drawerOpen,   () => this.queueRedraw());

                            // Livewire's morph rewrites the SVG <path> elements without
                            // a `d` attribute (the d is set imperatively in JS) · which
                            // means autosaves wipe every visible wire. Register one
                            // page-wide morph hook (guarded with a window flag so it
                            // is only installed once even if multiple canvases mount)
                            // and broadcast a redraw event each canvas instance listens
                            // for. Keeps the hook from interfering with active drag /
                            // pointer interactions on the palette buttons.
                            if (window.Livewire && ! window.__psNeMorphHookInstalled) {
                                window.__psNeMorphHookInstalled = true;
                                window.Livewire.hook('morphed', ({ el }) => {
                                    document.querySelectorAll('.ps-ne-canvas-wrap').forEach(c => {
                                        if (c === el || c.contains(el) || el.contains?.(c)) {
                                            c.dispatchEvent(new CustomEvent('ps-ne:morphed'));
                                        }
                                    });
                                });
                            }
                            this.$root.addEventListener('ps-ne:morphed', () => this.queueRedraw());

                            // Drag-to-scrub on any number input in node settings ·
                            // pointerdown grabs the value, pointermove updates it
                            // proportional to the horizontal cursor delta.
                            document.addEventListener('pointerdown', this.maybeStartScrub.bind(this));

                            // Keyboard shortcuts · only fire when an input/
                            // textarea isn't focused so typing in settings
                            // doesn't blow away nodes.
                            window.addEventListener('keydown', (ev) => {
                                const t = ev.target;
                                const inField = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable);
                                const mod = ev.ctrlKey || ev.metaKey;

                                if (mod && ev.key.toLowerCase() === 'd' && this.$wire.selectedNodeId) {
                                    ev.preventDefault();
                                    this.$wire.duplicateNode(this.$wire.selectedNodeId);
                                    return;
                                }
                                if (mod && ev.key.toLowerCase() === 'z') {
                                    ev.preventDefault();
                                    ev.shiftKey ? this.$wire.redo() : this.$wire.undo();
                                    return;
                                }
                                if (mod && ev.key.toLowerCase() === 'y') {
                                    ev.preventDefault();
                                    this.$wire.redo();
                                    return;
                                }
                                if (! inField && (ev.key === 'Delete' || ev.key === 'Backspace')) {
                                    const ids = Array.from(this.selectedIds);
                                    if (ids.length) {
                                        ev.preventDefault();
                                        this.$wire.removeNodes(ids);
                                        this.selectedIds = new Set();
                                    } else if (this.$wire.selectedNodeId) {
                                        ev.preventDefault();
                                        this.$wire.removeNode(this.$wire.selectedNodeId);
                                    }
                                }
                                if (! inField && mod && ev.key.toLowerCase() === 'c') {
                                    // Copy · grab the multi-select OR the
                                    // single selection if multi is empty.
                                    let ids = Array.from(this.selectedIds);
                                    if (! ids.length && this.$wire.selectedNodeId) ids = [this.$wire.selectedNodeId];
                                    if (! ids.length) return;
                                    ev.preventDefault();
                                    const set = new Set(ids);
                                    const nodes = (this.$wire.nodes || []).filter(n => set.has(n.id));
                                    // Keep only edges that live entirely within
                                    // the copied subgraph; external wires would
                                    // be ambiguous on paste.
                                    const edges = (this.$wire.edges || []).filter(e => set.has(e.from_node) && set.has(e.to_node));
                                    window.__psNodeClipboard = JSON.parse(JSON.stringify({ nodes, edges }));
                                    this.showCopyToast(nodes.length);
                                }
                                if (! inField && mod && ev.key.toLowerCase() === 'v') {
                                    const cb = window.__psNodeClipboard;
                                    if (! cb || ! cb.nodes || ! cb.nodes.length) return;
                                    ev.preventDefault();
                                    this.$wire.pasteSubgraph(cb.nodes, cb.edges || [], 40, 40);
                                }
                            });
                        },

                        maybeStartScrub(e) {
                            const input = e.target;
                            if (! input || input.tagName !== 'INPUT' || input.type !== 'number') return;
                            // Only scrub when the user starts WITHOUT focusing
                            // first · so click-then-type still works for direct
                            // entry. Trigger on shift- or middle-button drag, OR
                            // drag from outside the input that started on it.
                            if (e.button !== 0) return;
                            const startVal = parseFloat(input.value || '0') || 0;
                            const startX = e.clientX;
                            // Step size · use the input's step attr if present,
                            // else 0.05 for fractional, 1 for integers.
                            const step = parseFloat(input.step) || (Number.isInteger(startVal) ? 1 : 0.05);
                            let moved = false;
                            const onMove = (ev) => {
                                const dx = ev.clientX - startX;
                                if (! moved && Math.abs(dx) < 4) return;
                                moved = true;
                                const next = startVal + dx * step;
                                input.value = Number.isInteger(step) ? Math.round(next) : next.toFixed(2);
                                input.dispatchEvent(new Event('input', { bubbles: true }));
                                ev.preventDefault();
                            };
                            const onUp = () => {
                                window.removeEventListener('pointermove', onMove);
                                window.removeEventListener('pointerup',   onUp);
                                // If the pointer never moved, let the click stand
                                // so the user can still type normally.
                            };
                            window.addEventListener('pointermove', onMove);
                            window.addEventListener('pointerup',   onUp);
                        },

                        fitViewBox() {
                            const r = this.$root.getBoundingClientRect();
                            if (r.width > 0 && r.height > 0) {
                                this.viewBox = `0 0 ${Math.max(800, r.width)} ${Math.max(400, r.height)}`;
                            }
                        },

                        queueRedraw() {
                            if (this.rafToken) cancelAnimationFrame(this.rafToken);
                            this.rafToken = requestAnimationFrame(() => this.redrawWires());
                        },

                        // Autosave · debounced so a flurry of edits (drag,
                        // type, scrub) only triggers one round-trip when the
                        // user pauses.
                        autosaveTimer: null,
                        queueAutosave() {
                            if (this.autosaveTimer) clearTimeout(this.autosaveTimer);
                            this.autosaveTimer = setTimeout(() => this.$wire.saveGraph(), 600);
                        },

                        redrawWires() {
                            const canvas = this.$root;
                            const stage = canvas.querySelector('.ps-ne-stage');
                            if (! stage) return;
                            const stageRect = stage.getBoundingClientRect();
                            const z = this.zoom || 1;
                            canvas.querySelectorAll('.ps-ne-wire').forEach((path) => {
                                const fn = path.getAttribute('data-from-node');
                                const fs = path.getAttribute('data-from-socket');
                                const tn = path.getAttribute('data-to-node');
                                const ts = path.getAttribute('data-to-socket');
                                const from = canvas.querySelector(
                                    `.ps-ne-socket[data-socket-node="${fn}"][data-socket-key="${fs}"][data-socket-kind="out"]`,
                                );
                                const to = canvas.querySelector(
                                    `.ps-ne-socket[data-socket-node="${tn}"][data-socket-key="${ts}"][data-socket-kind="in"]`,
                                );
                                if (! from || ! to) { path.setAttribute('d', ''); return; }
                                const a = from.getBoundingClientRect();
                                const b = to.getBoundingClientRect();
                                // SVG lives inside the transformed stage · convert
                                // viewport coords to stage-local by subtracting the
                                // stage origin and dividing by zoom.
                                const x1 = (a.left + a.width / 2 - stageRect.left) / z;
                                const y1 = (a.top  + a.height / 2 - stageRect.top)  / z;
                                const x2 = (b.left + b.width / 2 - stageRect.left) / z;
                                const y2 = (b.top  + b.height / 2 - stageRect.top)  / z;
                                const bx = path.getAttribute('data-bend-x');
                                const by = path.getAttribute('data-bend-y');
                                if (bx !== null && by !== null) {
                                    // Manual reroute · route through the bend
                                    // point with smooth bezier segments either
                                    // side of it.
                                    const mx = parseFloat(bx), my = parseFloat(by);
                                    const d1 = Math.max(40, Math.abs(mx - x1) * 0.4);
                                    const d2 = Math.max(40, Math.abs(x2 - mx) * 0.4);
                                    path.setAttribute('d',
                                        `M ${x1} ${y1} C ${x1 + d1} ${y1}, ${mx - d1} ${my}, ${mx} ${my}` +
                                        ` S ${x2 - d2} ${y2}, ${x2} ${y2}`,
                                    );
                                } else {
                                    const dx = Math.max(60, Math.abs(x2 - x1) * 0.4);
                                    path.setAttribute('d',
                                        `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`,
                                    );
                                }
                                // Tint by the source output type · flag when
                                // a typed output flows into a typed input of a
                                // different type so the user sees the coercion.
                                const fromType = from.getAttribute('data-socket-type') || 'any';
                                const toType   = to.getAttribute('data-socket-type')   || 'any';
                                path.setAttribute('data-wire-type', fromType);
                                path.setAttribute(
                                    'data-wire-warn',
                                    fromType !== 'any' && toType !== 'any' && fromType !== toType ? '1' : '0',
                                );
                            });
                        },

                        // ─── Drag-to-connect ─────────────────────────────────────
                        // Pointerdown on an output socket starts a "ghost" wire
                        // that tracks the cursor; releasing over an input socket
                        // commits the connection.
                        startSocketDrag(e, fromNode, fromSocket, fromType) {
                            e.stopPropagation();
                            e.preventDefault();
                            const stage = this.$root.querySelector('.ps-ne-stage');
                            const stageRect = stage.getBoundingClientRect();
                            const z = this.zoom || 1;

                            // Source socket centre in stage-local coords.
                            const srcEl = e.currentTarget;
                            const sr = srcEl.getBoundingClientRect();
                            const x1 = (sr.left + sr.width / 2 - stageRect.left) / z;
                            const y1 = (sr.top  + sr.height / 2 - stageRect.top)  / z;

                            const ghost = document.getElementById('ps-ne-ghost-wire');
                            ghost.setAttribute('data-wire-type', fromType || 'any');

                            const onMove = (ev) => {
                                const x2 = (ev.clientX - stageRect.left) / z;
                                const y2 = (ev.clientY - stageRect.top)  / z;
                                const dx = Math.max(60, Math.abs(x2 - x1) * 0.4);
                                ghost.setAttribute('d',
                                    `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`,
                                );
                            };
                            const onUp = (ev) => {
                                window.removeEventListener('pointermove', onMove);
                                window.removeEventListener('pointerup',   onUp);
                                ghost.setAttribute('d', '');
                                const drop = document.elementFromPoint(ev.clientX, ev.clientY);
                                const target = drop && drop.closest('.ps-ne-socket--in');
                                if (target) {
                                    const toNode   = target.getAttribute('data-socket-node');
                                    const toSocket = target.getAttribute('data-socket-key');
                                    if (toNode !== fromNode) {
                                        this.$wire.startConnection(fromNode, fromSocket);
                                        this.$wire.completeConnection(toNode, toSocket);
                                    }
                                    return;
                                }
                                // Dropped on the canvas background · open the
                                // quick-add picker so the user can spawn a new
                                // node pre-wired from this socket.
                                const onCanvas = drop && (drop.closest('.ps-ne-canvas-wrap') || drop.closest('.ps-ne-stage'));
                                if (onCanvas) {
                                    const rect = this.$root.getBoundingClientRect();
                                    const stagePt = this.toStage(ev.clientX, ev.clientY);
                                    this.quickAdd = {
                                        open: true,
                                        x: ev.clientX - rect.left,
                                        y: ev.clientY - rect.top,
                                        canvasX: Math.max(0, Math.round(stagePt.x - 60)),
                                        canvasY: Math.max(0, Math.round(stagePt.y - 12)),
                                        fromNode, fromSocket, fromType,
                                    };
                                }
                            };
                            window.addEventListener('pointermove', onMove);
                            window.addEventListener('pointerup',   onUp);
                        },

                        startNodeDrag(e, nodeId) {
                            // Skip drags that started on an interactive child
                            // (sockets, buttons) so they keep their own click.
                            if (e.target.closest('.ps-ne-socket, .ps-ne-node-remove')) return;
                            const nodeEl = this.$root.querySelector(`.ps-ne-node[data-node-id="${nodeId}"]`);
                            if (! nodeEl) return;
                            const m = nodeEl.style.transform.match(/translate\(([-\d.]+)px, ?([-\d.]+)px\)/);

                            const state = {
                                startX: e.clientX,
                                startY: e.clientY,
                                originX: m ? parseFloat(m[1]) : 0,
                                originY: m ? parseFloat(m[2]) : 0,
                                lastX: m ? parseFloat(m[1]) : 0,
                                lastY: m ? parseFloat(m[2]) : 0,
                            };

                            const z = this.zoom || 1;
                            const SNAP = 20;
                            const onMove = (ev) => {
                                let nx = Math.max(0, state.originX + (ev.clientX - state.startX) / z);
                                let ny = Math.max(0, state.originY + (ev.clientY - state.startY) / z);
                                // Hold Shift to bypass snap · matches Blender.
                                if (! ev.shiftKey) {
                                    nx = Math.round(nx / SNAP) * SNAP;
                                    ny = Math.round(ny / SNAP) * SNAP;
                                }
                                state.lastX = nx;
                                state.lastY = ny;
                                nodeEl.style.transform = `translate(${state.lastX}px, ${state.lastY}px)`;
                                this.queueRedraw();
                            };
                            const onUp = () => {
                                window.removeEventListener('pointermove', onMove);
                                window.removeEventListener('pointerup',   onUp);
                                this.$wire.moveNode(nodeId, Math.round(state.lastX), Math.round(state.lastY));
                            };
                            window.addEventListener('pointermove', onMove);
                            window.addEventListener('pointerup',   onUp);
                            nodeEl.setPointerCapture?.(e.pointerId);
                            e.preventDefault();
                        },
                    };
                };
            </script>
        @endpush
        @push('scripts')
            <style>
                .ps-page-builder {
                    --rail-w: 9rem;
                    --rail-w-expanded: 14rem;
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    min-height: 0;
                }
                .ps-pb-topbar {
                    display: flex;
                    align-items: center;
                    gap: .75rem;
                    padding: .5rem .85rem;
                    background: var(--surface-2, #1E1F22);
                    border-bottom: 1px solid var(--line, #3A3D40);
                    flex-shrink: 0;
                }
                .ps-pb-route { display: flex; align-items: center; gap: .55rem; font-family: ui-monospace, monospace; font-size: .85rem; }
                .ps-pb-method { background: rgba(34,197,94,.2); color: #22c55e; border-radius: .25rem; padding: .1rem .4rem; font-weight: 700; font-size: .7rem; }
                .ps-pb-path { color: var(--ink, #F0EDE5); }
                .ps-pb-actions { margin-left: auto; display: flex; gap: .5rem; align-items: center; }
                .ps-pb-btn {
                    background: transparent;
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5);
                    padding: .35rem .75rem;
                    border-radius: .35rem;
                    cursor: pointer;
                    font: inherit;
                    font-size: .8rem;
                }
                .ps-pb-btn.is-active { background: rgba(255,255,255,.08); }
                .ps-pb-btn--primary { background: var(--accent, #2C66E8); border-color: var(--accent, #2C66E8); color: #fff; }
                .ps-pb-btn:disabled { opacity: .65; cursor: not-allowed; }
                .ps-pb-save-btn { min-width: 7rem; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; }
                .ps-pb-save-btn.is-saving { background: color-mix(in srgb, var(--accent, #2C66E8) 70%, #000); }
                .ps-pb-save-busy { display: inline-flex; align-items: center; gap: .4rem; }
                .ps-pb-spinner {
                    width: .85rem;
                    height: .85rem;
                    border: 2px solid rgba(255,255,255,.35);
                    border-top-color: #fff;
                    border-radius: 50%;
                    display: inline-block;
                    animation: ps-spin .7s linear infinite;
                }
                @keyframes ps-spin { to { transform: rotate(360deg); } }
                .ps-pb-saved-stamp {
                    font-size: .7rem;
                    color: var(--ink-dim, #A3A099);
                    font-variant-numeric: tabular-nums;
                    margin-right: .15rem;
                    white-space: nowrap;
                }
                .ps-pb-diff-stamp {
                    font-size: .68rem;
                    color: var(--accent, #2C66E8);
                    font-variant-numeric: tabular-nums;
                    background: rgba(44,102,232,.10);
                    border: 1px solid rgba(44,102,232,.30);
                    padding: .1rem .4rem;
                    border-radius: .3rem;
                    white-space: nowrap;
                    margin-right: .15rem;
                }

                /* Lifecycle badge · Draft (neutral) / Scheduled (amber) / Published (green) */
                .ps-pb-status-badge {
                    display: inline-flex; align-items: center;
                    padding: .15rem .55rem;
                    border-radius: 999px;
                    font-size: .65rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    border: 1px solid transparent;
                    line-height: 1.4;
                }
                .ps-pb-status-badge--draft     { background: rgba(163,160,153,.18); color: #A3A099; border-color: rgba(163,160,153,.3); }
                .ps-pb-status-badge--scheduled { background: rgba(245,158,11,.18); color: #f59e0b; border-color: rgba(245,158,11,.35); }
                .ps-pb-status-badge--published { background: rgba(34,197,94,.18);  color: #22c55e; border-color: rgba(34,197,94,.35); }
                .ps-pb-publish-at {
                    display: inline-flex; align-items: center; gap: .3rem;
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    padding: 0 .4rem;
                    height: 1.85rem;
                    font-size: .75rem;
                    color: var(--ink-dim, #A3A099);
                    background: transparent;
                }
                .ps-pb-publish-at-input {
                    background: transparent;
                    border: 0;
                    color: var(--ink, #F0EDE5);
                    font: inherit;
                    font-size: .75rem;
                    outline: none;
                }
                .ps-pb-visually-hidden {
                    position: absolute !important;
                    width: 1px; height: 1px;
                    padding: 0; margin: -1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    white-space: nowrap;
                    border: 0;
                }
                .ps-pb-rail-toggle {
                    background: transparent;
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink-dim, #A3A099);
                    padding: 0 .45rem;
                    height: 1.75rem;
                    border-radius: .3rem;
                    cursor: pointer;
                    font: inherit;
                    font-size: .7rem;
                    line-height: 1;
                    flex-shrink: 0;
                }
                .ps-pb-rail-toggle:hover { color: var(--ink, #F0EDE5); background: rgba(255,255,255,.05); }

                .ps-pb-grid {
                    display: grid;
                    grid-template-columns: var(--rail-w) 1fr var(--rail-w);
                    grid-template-areas: "left canvas right";
                    gap: 0;
                    flex: 1;
                    min-height: 0;
                    overflow: hidden;
                    transition: grid-template-columns .15s ease;
                }
                .ps-pb-grid.is-left-collapsed  { grid-template-columns: 0 1fr var(--rail-w); }
                .ps-pb-grid.is-right-collapsed { grid-template-columns: var(--rail-w) 1fr 0; }
                .ps-pb-grid.is-left-collapsed.is-right-collapsed { grid-template-columns: 0 1fr 0; }

                /* ─── Mobile · stack to a single column, rails turn into
                       slide-over sheets controlled by the toolbar toggles.
                       The bottom drawer (node editor) becomes a full-screen
                       overlay so touch users get a real surface to work on. */
                @media (max-width: 768px) {
                    .ps-pb-grid {
                        grid-template-columns: 1fr;
                        grid-template-areas: "canvas";
                    }
                    .ps-pb-grid.is-left-collapsed,
                    .ps-pb-grid.is-right-collapsed,
                    .ps-pb-grid.is-left-collapsed.is-right-collapsed {
                        grid-template-columns: 1fr;
                    }
                    .ps-pb-rail {
                        position: fixed;
                        top: 0;
                        bottom: 0;
                        width: min(85vw, 22rem);
                        z-index: 400;
                        background: var(--surface-2, #1E1F22);
                        transition: transform .2s ease;
                        box-shadow: 0 8px 32px rgba(0,0,0,.45);
                    }
                    .ps-pb-rail--left  { left: 0;  transform: translateX(-100%); grid-area: unset; }
                    .ps-pb-rail--right { right: 0; transform: translateX(100%);  grid-area: unset; }
                    /* On mobile the `is-...-collapsed` class means "closed" ·
                       remove it (by tapping the toggle) and the rail slides in. */
                    .ps-pb-grid:not(.is-left-collapsed)  .ps-pb-rail--left  { transform: translateX(0); }
                    .ps-pb-grid:not(.is-right-collapsed) .ps-pb-rail--right { transform: translateX(0); }
                    .ps-pb-canvas-wrap { padding: .75rem; }
                    .ps-pb-canvas { padding: .9rem; border-radius: .25rem; }
                    /* Backdrop · clicking outside the rail closes it. */
                    .ps-pb-grid:not(.is-left-collapsed)::before,
                    .ps-pb-grid:not(.is-right-collapsed)::after {
                        content: '';
                        position: fixed; inset: 0; z-index: 399;
                        background: rgba(0,0,0,.45);
                    }
                    /* Toolbar wraps on narrow screens · save / preview keep
                       their CTA-shape but the URL chip + toggles flow. */
                    .ps-pb-toolbar { flex-wrap: wrap; gap: .35rem; padding: .5rem .65rem; }
                    .ps-pb-toolbar code.ps-pb-path { font-size: .75rem; }
                    /* Drawer fills the viewport bottom · resize grabber is
                       disabled because pointer events fight touch scroll. */
                    .ps-ne-drawer { height: 80vh !important; }
                    .ps-ne-grabber { display: none; }
                    .ps-ne-grid    { grid-template-columns: 1fr; grid-template-areas: "canvas"; }
                    .ps-ne-palette,
                    .ps-ne-settings {
                        position: fixed;
                        top: 20vh; bottom: 0;
                        width: min(85vw, 22rem);
                        z-index: 401;
                        background: var(--surface-2, #1E1F22);
                        transform: translateX(-100%);
                        transition: transform .2s ease;
                    }
                    .ps-ne-settings { right: 0; left: auto; transform: translateX(100%); }
                    /* Finder overlay · fill the upper half of the viewport on phones. */
                    .ps-pb-find { width: 96vw; top: 6vh; }
                    .ps-pb-find-row { grid-template-columns: 3rem 1.25rem 1fr; }
                    .ps-pb-find-preview { display: none; }
                }
                .ps-pb-rail {
                    background: rgba(255,255,255,.02);
                    padding: .65rem .55rem;
                    overflow-y: auto;
                    min-width: 0;
                }
                .ps-pb-rail--left   { border-right: 1px solid var(--line, #3A3D40); grid-area: left; }
                .ps-pb-rail--right  { border-left:  1px solid var(--line, #3A3D40); grid-area: right; }
                .ps-pb-canvas-wrap  { grid-area: canvas; background: var(--surface, #16171a); padding: 1.25rem; overflow-y: auto; }
                .ps-pb-canvas {
                    background: #fff;
                    color: #111;
                    border-radius: .35rem;
                    padding: 1.5rem;
                    min-height: 100%;
                    max-width: 56rem;
                    margin: 0 auto;
                    box-shadow: 0 4px 16px rgba(0,0,0,.25);
                }
                .ps-pb-canvas-empty { color: #888; text-align: center; padding: 4rem 1rem; font-style: italic; }
                .ps-pb-canvas-empty.is-drop-target { border: 2px dashed var(--accent, #2C66E8); background: rgba(44,102,232,.05); border-radius: .35rem; }

                .ps-pb-section { margin-bottom: 1.1rem; }
                .ps-pb-section h3 {
                    margin: 0 0 .4rem;
                    font-size: .65rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-pb-hint { color: var(--ink-dim, #A3A099); font-size: .75rem; margin: .35rem 0; }
                .ps-pb-palette { display: grid; grid-template-columns: 1fr; gap: .25rem; }
                .ps-pb-palette-item {
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5);
                    border-radius: .3rem;
                    padding: .35rem .5rem;
                    cursor: grab;
                    font: inherit;
                    font-size: .75rem;
                    display: flex;
                    flex-direction: row;
                    align-items: center;
                    gap: .45rem;
                    text-align: left;
                }
                .ps-pb-palette-item:active { cursor: grabbing; }
                .ps-pb-palette-item:hover { background: rgba(255,255,255,.06); }
                .ps-pb-palette-icon { font-size: 1.05rem; line-height: 1; }
                .ps-pb-vars { display: flex; flex-direction: column; gap: .25rem; }
                .ps-pb-var-chip {
                    display: inline-block;
                    background: color-mix(in srgb, var(--accent, #2C66E8) 22%, transparent);
                    color: var(--accent, #2C66E8);
                    border-radius: .3rem;
                    padding: .2rem .5rem;
                    font-family: ui-monospace, monospace;
                    font-size: .75rem;
                    cursor: grab;
                    user-select: none;
                }
                .ps-pb-var-chip:active { cursor: grabbing; }

                /* Canvas block chrome */
                .ps-pb-block-wrap {
                    position: relative;
                    padding: .4rem;
                    border-radius: .3rem;
                    border: 2px solid transparent;
                    margin-bottom: .35rem;
                    cursor: pointer;
                }
                .ps-pb-block-wrap:hover { background: rgba(44,102,232,.04); border-color: rgba(44,102,232,.18); }
                .ps-pb-block-wrap.is-selected { background: rgba(44,102,232,.06); border-color: var(--accent, #2C66E8); }
                .ps-pb-block-handle {
                    display: none;
                    position: absolute;
                    top: -.85rem;
                    left: 0;
                    background: var(--accent, #2C66E8);
                    color: #fff;
                    border-radius: .25rem;
                    padding: .15rem .4rem;
                    font-size: .7rem;
                    font-family: ui-monospace, monospace;
                    z-index: 4;
                    align-items: center;
                    gap: .35rem;
                }
                .ps-pb-block-wrap.is-selected > .ps-pb-block-handle,
                .ps-pb-block-wrap:hover > .ps-pb-block-handle { display: inline-flex; }
                .ps-pb-block-type { letter-spacing: .03em; }
                .ps-pb-block-controls { display: flex; gap: .15rem; }
                .ps-pb-block-controls button {
                    background: transparent; border: 0; color: #fff;
                    padding: 0 .25rem; cursor: pointer; font: inherit; font-size: .75rem;
                }
                .ps-pb-block-danger { color: #fee2e2 !important; }

                /* Block comment pip · small red circle anchored top-right
                   of the block-wrap. Always visible when an open comment
                   exists, regardless of hover / selection state. */
                .ps-pb-block-comment-pip {
                    position: absolute;
                    top: -.4rem;
                    right: -.4rem;
                    min-width: 1rem;
                    height: 1rem;
                    padding: 0 .3rem;
                    border-radius: 1rem;
                    background: #DC2626;
                    color: #fff;
                    border: 0;
                    font-size: .65rem;
                    line-height: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    z-index: 5;
                    box-shadow: 0 1px 2px rgba(0,0,0,.25);
                }
                .ps-pb-block-comment-pip:hover { background: #B91C1C; }

                /* Right-rail tabs · flip between Settings and Comments. */
                .ps-pb-rail-tabs {
                    display: flex;
                    gap: .25rem;
                    padding: .5rem .5rem 0;
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-rail-tab {
                    background: transparent;
                    color: var(--muted, #999);
                    border: 0;
                    padding: .4rem .6rem;
                    font: inherit;
                    font-size: .75rem;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                    display: inline-flex;
                    align-items: center;
                    gap: .35rem;
                }
                .ps-pb-rail-tab:hover { color: var(--ink, #F0EDE5); }
                .ps-pb-rail-tab.is-active {
                    color: var(--ink, #F0EDE5);
                    border-bottom-color: var(--accent, #2C66E8);
                }
                .ps-pb-rail-tab-pip {
                    display: inline-block;
                    min-width: 1.1rem;
                    padding: 0 .3rem;
                    border-radius: 1rem;
                    background: #DC2626;
                    color: #fff;
                    font-size: .6rem;
                    line-height: 1rem;
                    font-weight: 600;
                }

                /* Comments panel · threads + compose form share the rail. */
                .ps-pb-comments-section { padding-bottom: 1rem; }
                .ps-pb-comment-thread {
                    margin-bottom: .75rem;
                    padding: .35rem 0;
                    border-bottom: 1px solid rgba(255,255,255,.04);
                }
                .ps-pb-comment-thread.is-selected-block {
                    border-color: rgba(44,102,232,.35);
                }
                .ps-pb-comment-row {
                    padding: .4rem .5rem;
                    border-radius: .3rem;
                    background: rgba(255,255,255,.03);
                    margin-bottom: .35rem;
                }
                .ps-pb-comment-reply {
                    margin-left: 1.25rem;
                    background: rgba(255,255,255,.02);
                    border-left: 2px solid var(--line, #3A3D40);
                }
                .ps-pb-comment-head {
                    display: flex;
                    justify-content: space-between;
                    align-items: baseline;
                    gap: .5rem;
                    margin-bottom: .2rem;
                }
                .ps-pb-comment-author { font-weight: 600; font-size: .8rem; }
                .ps-pb-comment-time { font-size: .65rem; color: var(--muted, #999); }
                .ps-pb-comment-body {
                    font-size: .8rem;
                    line-height: 1.4;
                    white-space: pre-wrap;
                    word-break: break-word;
                }
                .ps-pb-comment-actions {
                    display: flex;
                    gap: .25rem;
                    margin-top: .35rem;
                }
                .ps-pb-comment-action {
                    background: transparent;
                    border: 0;
                    color: var(--muted, #999);
                    cursor: pointer;
                    font: inherit;
                    font-size: .7rem;
                    padding: .15rem .3rem;
                    border-radius: .2rem;
                }
                .ps-pb-comment-action:hover {
                    color: var(--ink, #F0EDE5);
                    background: rgba(255,255,255,.05);
                }
                .ps-pb-comment-danger:hover { color: #fee2e2; background: rgba(220,38,38,.15); }
                .ps-pb-comment-compose {
                    margin-top: .75rem;
                    padding-top: .5rem;
                    border-top: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-comment-compose textarea {
                    width: 100%;
                    background: rgba(0,0,0,.2);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .3rem;
                    padding: .4rem;
                    font: inherit;
                    font-size: .8rem;
                    resize: vertical;
                    margin-bottom: .4rem;
                }
                .ps-pb-comment-replying {
                    font-size: .7rem;
                    color: var(--muted, #999);
                    margin-bottom: .35rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .ps-pb-comment-block-jump {
                    background: transparent;
                    border: 1px dashed var(--line, #3A3D40);
                    color: var(--muted, #999);
                    padding: .25rem .4rem;
                    font: inherit;
                    font-size: .7rem;
                    border-radius: .25rem;
                    cursor: pointer;
                    margin-bottom: .3rem;
                }
                .ps-pb-comments-btn.is-active { color: var(--accent, #2C66E8); }

                /* Drop indicator */
                .ps-pb-drop-line {
                    height: 0;
                    border-top: 2px dashed var(--accent, #2C66E8);
                    margin: .35rem 0;
                    border-radius: 2px;
                    box-shadow: 0 0 8px rgba(44,102,232,.35);
                }

                /* Resolved variable values · highlighted in the editor canvas */
                .ps-pb-block-render .ps-var,
                .ps-pb-layout-frame .ps-var {
                    background: color-mix(in srgb, #2C66E8 16%, transparent);
                    color: #2C66E8;
                    border-radius: .2rem;
                    padding: 0 .3rem;
                    font-weight: 600;
                }

                /* Layout containers · render slots as drop targets in editor mode */
                .ps-pb-layout-frame {
                    display: grid;
                    gap: .55rem;
                    padding: .3rem;
                    border-radius: .25rem;
                }
                .ps-pb-layout--slots-1 { grid-template-columns: 1fr; }
                .ps-pb-layout--slots-2 { grid-template-columns: 1fr 1fr; }
                .ps-pb-layout--slots-3 { grid-template-columns: 1fr 1fr 1fr; }
                .ps-pb-slot {
                    border: 1px dashed #cbd5e1;
                    border-radius: .35rem;
                    padding: .35rem .45rem .55rem;
                    background: #fafbfc;
                    min-height: 4rem;
                }
                .ps-pb-slot-label {
                    font-size: .65rem;
                    color: #6b7280;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    margin-bottom: .3rem;
                }
                .ps-pb-slot-body { min-height: 2.5rem; }
                .ps-pb-slot-empty {
                    color: #94a3b8;
                    font-style: italic;
                    font-size: .8rem;
                    padding: .85rem .5rem;
                    text-align: center;
                    border-radius: .25rem;
                }
                .ps-pb-slot-empty.is-active {
                    background: rgba(44,102,232,.08);
                    color: var(--accent, #2C66E8);
                }

                /* Settings panel */
                .ps-pb-field { margin-bottom: .65rem; }
                .ps-pb-field-head {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: .35rem;
                    margin-bottom: .2rem;
                }
                .ps-pb-field label {
                    display: block;
                    font-size: .7rem;
                    color: var(--ink-dim, #A3A099);
                    text-transform: uppercase;
                    letter-spacing: .03em;
                }
                .ps-pb-var-btn {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 18%, transparent);
                    border: 1px solid color-mix(in srgb, var(--accent, #2C66E8) 40%, transparent);
                    color: var(--accent, #2C66E8);
                    border-radius: .25rem;
                    padding: .1rem .4rem;
                    font: inherit;
                    font-size: .65rem;
                    font-family: ui-monospace, monospace;
                    cursor: pointer;
                    flex-shrink: 0;
                }
                .ps-pb-var-btn:hover { background: color-mix(in srgb, var(--accent, #2C66E8) 28%, transparent); }
                .ps-pb-field input,
                .ps-pb-field select,
                .ps-pb-field textarea {
                    width: 100%;
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .3rem;
                    padding: .4rem .55rem;
                    font: inherit;
                    font-size: .8rem;
                    box-sizing: border-box;
                    resize: vertical;
                }

                /* While dragging a variable, signal which fields accept it.
                   Any input/textarea inside .ps-pb-field with a wire:model is
                   treated as droppable; selects are not (they pick from a
                   fixed list). */
                .ps-pb-dragging-var .ps-pb-field input[wire\:model],
                .ps-pb-dragging-var .ps-pb-field input[wire\:model\.live],
                .ps-pb-dragging-var .ps-pb-field input[wire\:model\.live\.debounce\.300ms],
                .ps-pb-dragging-var .ps-pb-field textarea[wire\:model],
                .ps-pb-dragging-var .ps-pb-field textarea[wire\:model\.live],
                .ps-pb-dragging-var .ps-pb-field textarea[wire\:model\.live\.debounce\.300ms] {
                    box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #2C66E8) 40%, transparent);
                    border-color: var(--accent, #2C66E8);
                }
                .ps-pb-dragging-var .ps-pb-field select {
                    opacity: .55;
                }

                /* Variable-drop visual feedback */
                .ps-pb-field [data-ps-var-drop] {
                    outline: 2px dashed var(--accent, #2C66E8);
                    outline-offset: 1px;
                    background: color-mix(in srgb, var(--accent, #2C66E8) 10%, var(--surface-2, #1E1F22));
                }

                /* Variable picker · right-click on any text field opens this */
                .ps-pb-var-picker {
                    position: fixed;
                    z-index: 300;
                    min-width: 14rem;
                    max-width: 18rem;
                    max-height: 18rem;
                    overflow-y: auto;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 8px 24px rgba(0,0,0,.5);
                    padding: .25rem;
                }
                .ps-pb-var-picker-header {
                    font-size: .65rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    color: var(--ink-dim, #A3A099);
                    padding: .35rem .5rem .25rem;
                }
                .ps-pb-var-picker-empty {
                    padding: .5rem .65rem;
                    color: var(--ink-dim, #A3A099);
                    font-style: italic;
                    font-size: .8rem;
                }
                .ps-pb-var-picker-item {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: .15rem;
                    width: 100%;
                    background: transparent;
                    border: 0;
                    border-radius: .25rem;
                    text-align: left;
                    padding: .35rem .5rem;
                    cursor: pointer;
                    font: inherit;
                    color: var(--ink, #F0EDE5);
                }
                .ps-pb-var-picker-item code {
                    font-family: ui-monospace, monospace;
                    color: var(--accent, #2C66E8);
                    font-size: .8rem;
                }
                .ps-pb-var-picker-preview {
                    color: var(--ink-dim, #A3A099);
                    font-size: .7rem;
                    font-family: ui-monospace, monospace;
                }
                .ps-pb-var-picker-item:hover {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 18%, transparent);
                }


                /* Toast · fixed bottom-right so it floats over everything,
                   never gets clipped by the layout's overflow:hidden. */
                .ps-pb-toast {
                    position: fixed;
                    bottom: 1.25rem;
                    right: 1.25rem;
                    z-index: 400;
                    padding: .55rem .9rem;
                    border-radius: .35rem;
                    background: var(--accent, #2C66E8);
                    color: #fff;
                    font-size: .85rem;
                    font-weight: 500;
                    box-shadow: 0 8px 24px rgba(0,0,0,.45);
                    max-width: 22rem;
                }
                .ps-pb-toast.is-err { background: var(--danger, #ef4444); }

                /* Preview mode pane */
                .ps-pb-preview-wrap {
                    background: var(--surface, #16171a);
                    padding: 1.5rem;
                    flex: 1;
                    min-height: 0;
                    overflow-y: auto;
                }
                .ps-pb-preview-pane {
                    background: #fff;
                    color: #111;
                    border-radius: .35rem;
                    padding: 2rem;
                    max-width: 48rem;
                    margin: 0 auto;
                    box-shadow: 0 4px 16px rgba(0,0,0,.3);
                }
                .ps-pb-empty { color: #888; font-style: italic; text-align: center; margin: 0; }

                /* Email meta band · shown in email mode under the topbar */
                .ps-pb-email-meta {
                    display: grid;
                    grid-template-columns: 1fr 1fr 1fr;
                    gap: .65rem;
                    padding: .65rem .85rem;
                    background: rgba(255,255,255,.02);
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-email-meta-field {
                    display: flex;
                    flex-direction: column;
                    gap: .2rem;
                    font-size: .8rem;
                }
                .ps-pb-email-meta-field span {
                    color: var(--ink-dim, #A3A099);
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    font-size: .65rem;
                }
                .ps-pb-email-meta-field input {
                    background: var(--surface, #16171a);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .25rem;
                    padding: .35rem .55rem;
                    font-size: .85rem;
                    outline: none;
                }
                .ps-pb-email-meta-field input:focus {
                    border-color: var(--accent, #2C66E8);
                }
                @media (max-width: 768px) {
                    .ps-pb-email-meta { grid-template-columns: 1fr; gap: .4rem; }
                }

                /* In-page finder · Ctrl-F / '/' opens it */
                .ps-pb-find-wrap { position: fixed; inset: 0; z-index: 600; }
                .ps-pb-find-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.55); }
                .ps-pb-find {
                    position: absolute; top: 12vh; left: 50%;
                    transform: translateX(-50%);
                    width: min(640px, 92vw);
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .5rem;
                    box-shadow: 0 24px 64px rgba(0,0,0,.5);
                    overflow: hidden;
                }
                .ps-pb-find-input {
                    width: 100%;
                    padding: .9rem 1.1rem;
                    background: transparent;
                    border: 0;
                    border-bottom: 1px solid var(--line, #3A3D40);
                    color: inherit;
                    font-size: 1rem;
                    outline: none;
                }
                .ps-pb-find-results { max-height: 50vh; overflow-y: auto; padding: .25rem; }
                .ps-pb-find-hint { padding: 1rem; color: var(--ink-dim, #A3A099); font-size: .85rem; margin: 0; }
                .ps-pb-find-row {
                    display: grid;
                    grid-template-columns: 3.5rem 1.5rem 1fr 2fr;
                    gap: .5rem;
                    align-items: center;
                    width: 100%;
                    padding: .45rem .75rem;
                    background: transparent;
                    border: 0;
                    color: inherit;
                    text-align: left;
                    cursor: pointer;
                    border-radius: .3rem;
                }
                .ps-pb-find-row.is-active { background: color-mix(in srgb, #2C66E8 18%, transparent); }
                .ps-pb-find-kind {
                    font-size: .65rem;
                    letter-spacing: .08em;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-pb-find-icon { font-size: 1rem; }
                .ps-pb-find-label { font-family: ui-monospace, monospace; font-size: .85rem; }
                .ps-pb-find-preview { color: var(--ink-dim, #A3A099); font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

                /* Keyboard shortcuts cheat sheet · ? opens it */
                .ps-pb-cheats-wrap { position: fixed; inset: 0; z-index: 500; }
                .ps-pb-cheats-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.55); }
                .ps-pb-cheats {
                    position: absolute; top: 50%; left: 50%;
                    transform: translate(-50%, -50%);
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .5rem;
                    box-shadow: 0 16px 48px rgba(0,0,0,.6);
                    padding: 1.25rem 1.5rem;
                    width: 90%;
                    max-width: 32rem;
                }
                .ps-pb-cheats h3 { margin: 0 0 .85rem; font-size: 1rem; }
                .ps-pb-cheats table { width: 100%; border-collapse: collapse; font-size: .85rem; }
                .ps-pb-cheats th {
                    text-align: left; font-weight: 500;
                    color: var(--ink, #F0EDE5);
                    padding: .35rem .65rem .35rem 0;
                    font-family: ui-monospace, monospace;
                    font-size: .75rem;
                    border-bottom: 1px solid var(--line, #3A3D40);
                    white-space: nowrap;
                }
                .ps-pb-cheats td {
                    padding: .35rem 0;
                    color: var(--ink-dim, #A3A099);
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-cheats footer { display: flex; justify-content: flex-end; margin-top: 1rem; }

                /* Compare revisions · side-by-side overlay, mirrors the cheat-sheet shell */
                .ps-pb-compare-wrap { position: fixed; inset: 0; z-index: 510; }
                .ps-pb-compare-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,.6); }
                .ps-pb-compare {
                    position: absolute; top: 50%; left: 50%;
                    transform: translate(-50%, -50%);
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .5rem;
                    box-shadow: 0 16px 48px rgba(0,0,0,.6);
                    display: flex; flex-direction: column;
                    width: 92vw; max-width: 86rem;
                    height: 86vh;
                }
                .ps-pb-compare-head {
                    display: flex; align-items: center; gap: 1rem;
                    padding: .85rem 1.25rem;
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-compare-head h3 { margin: 0; font-size: .95rem; }
                .ps-pb-compare-diff {
                    margin-left: auto; display: flex; gap: .85rem;
                    font-family: ui-monospace, monospace; font-size: .8rem;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-pb-compare-pickers {
                    display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
                    padding: .75rem 1.25rem;
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-compare-pickers label {
                    display: flex; flex-direction: column; gap: .25rem;
                    font-size: .7rem; color: var(--ink-dim, #A3A099);
                    text-transform: uppercase; letter-spacing: .07em;
                }
                .ps-pb-compare-pickers select {
                    background: var(--surface, #16171A);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .3rem;
                    padding: .35rem .55rem;
                    font: inherit; font-size: .8rem;
                }
                .ps-pb-compare-cols {
                    flex: 1; min-height: 0;
                    display: grid; grid-template-columns: 1fr 1fr; gap: 0;
                }
                .ps-pb-compare-col {
                    display: flex; flex-direction: column;
                    border-right: 1px solid var(--line, #3A3D40);
                    min-height: 0;
                }
                .ps-pb-compare-col:last-child { border-right: 0; }
                .ps-pb-compare-col-head {
                    padding: .55rem 1rem;
                    font-size: .75rem;
                    font-family: ui-monospace, monospace;
                    color: var(--ink-dim, #A3A099);
                    border-bottom: 1px solid var(--line, #3A3D40);
                    background: rgba(255,255,255,.02);
                }
                .ps-pb-compare-col-body {
                    flex: 1; min-height: 0;
                    overflow-y: auto;
                    padding: 1rem 1.25rem;
                    background: #fff;
                    color: #1a1a1a;
                }

                /* Revisions dropdown · sits in the drawer bar */
                .ps-pb-revisions { position: relative; }
                .ps-pb-revisions summary { cursor: pointer; list-style: none; }
                .ps-pb-revisions summary::-webkit-details-marker { display: none; }
                .ps-pb-revisions-menu {
                    position: absolute; top: 100%; right: 0; margin-top: .3rem;
                    min-width: 16rem; max-height: 22rem; overflow-y: auto;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 8px 24px rgba(0,0,0,.5);
                    padding: .25rem;
                    z-index: 70;
                }
                .ps-pb-revisions-item {
                    display: flex; flex-direction: column; align-items: flex-start; gap: .1rem;
                    width: 100%; background: transparent; border: 0; cursor: pointer;
                    color: var(--ink, #F0EDE5); text-align: left;
                    padding: .35rem .55rem; border-radius: .25rem; font: inherit; font-size: .8rem;
                }
                .ps-pb-revisions-item:hover { background: rgba(255,255,255,.06); }
                .ps-pb-revisions-meta { color: var(--ink-dim, #A3A099); font-size: .7rem; }

                /* Multi-device preview frames · viewport widths a font-end
                   designer would actually test at. */
                .ps-pb-preview-toolbar {
                    display: flex; gap: .35rem; justify-content: center;
                    padding: .55rem 0; flex-shrink: 0;
                }
                .ps-pb-preview-toolbar button {
                    background: transparent; border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5); padding: .25rem .65rem;
                    border-radius: .3rem; font: inherit; font-size: .75rem; cursor: pointer;
                }
                .ps-pb-preview-toolbar button.is-active {
                    background: var(--accent, #2C66E8); border-color: var(--accent, #2C66E8); color: #fff;
                }
                .ps-pb-preview-pane--phone   { max-width: 23.5rem; }   /* ~375 px */
                .ps-pb-preview-pane--tablet  { max-width: 48rem;  }    /* ~768 px */
                .ps-pb-preview-pane--desktop { max-width: 64rem;  }

                /* Image upload preview · inside the settings panel */
                .ps-pb-upload-preview {
                    display: flex; align-items: center; gap: .5rem;
                    padding: .35rem; border-radius: .3rem;
                    background: rgba(255,255,255,.03);
                    border: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-upload-preview img {
                    width: 3rem; height: 3rem; object-fit: cover;
                    border-radius: .2rem; background: #0a0a0a;
                }

                /* Toposort order badge in the node header */
                .ps-ne-node-order {
                    background: rgba(255,255,255,.16);
                    color: var(--ink, #F0EDE5);
                    font-size: .55rem;
                    padding: .05rem .35rem;
                    border-radius: 99rem;
                    margin-left: .2rem;
                    font-variant-numeric: tabular-nums;
                }

                /* Outline tree · left rail block hierarchy view */
                .ps-pb-outline { display: flex; flex-direction: column; gap: 0; }
                .ps-pb-outline-row {
                    display: flex; align-items: center; gap: .35rem;
                    background: transparent; border: 0; color: var(--ink, #F0EDE5);
                    text-align: left; padding: .2rem .35rem;
                    font: inherit; font-size: .7rem; cursor: pointer;
                    border-radius: .2rem;
                    overflow: hidden;
                }
                .ps-pb-outline-row:hover { background: rgba(255,255,255,.05); }
                .ps-pb-outline-row.is-selected {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 22%, transparent);
                    color: var(--accent, #2C66E8);
                }
                .ps-pb-outline-icon { width: .75rem; text-align: center; flex-shrink: 0; }
                .ps-pb-outline-label { flex-shrink: 0; }
                .ps-pb-outline-snippet {
                    color: var(--ink-dim, #A3A099);
                    font-style: italic;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    flex: 1;
                }

                /* ─── Node-editor drawer ────────────────────────────────── */
                .ps-ne-drawer {
                    position: relative;
                    flex-shrink: 0;
                    background: var(--surface-2, #1E1F22);
                    border-top: 1px solid var(--line, #3A3D40);
                    display: flex;
                    flex-direction: column;
                }
                .ps-ne-grabber {
                    position: absolute;
                    top: -3px;
                    left: 0;
                    right: 0;
                    height: 6px;
                    cursor: ns-resize;
                    z-index: 35;
                }
                .ps-ne-grabber::before {
                    content: '';
                    position: absolute;
                    inset: 2px 0;
                    background: transparent;
                    transition: background-color .15s;
                }
                .ps-ne-grabber:hover::before { background: var(--accent, #2C66E8); opacity: .35; }

                .ps-ne-quick-add { z-index: 65; }
                .ps-ne-drawer-bar {
                    display: flex;
                    align-items: center;
                    gap: .65rem;
                    padding: .35rem .75rem;
                    background: rgba(0,0,0,.15);
                    border-bottom: 1px solid var(--line, #3A3D40);
                    flex-shrink: 0;
                }
                .ps-ne-title {
                    font-size: .75rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-ne-pending {
                    font-size: .75rem;
                    color: var(--accent, #2C66E8);
                    font-family: ui-monospace, monospace;
                }
                .ps-ne-pending code {
                    background: rgba(44,102,232,.18);
                    padding: 0 .35rem;
                    border-radius: .25rem;
                }
                .ps-ne-drawer-actions { margin-left: auto; display: flex; gap: .4rem; }

                .ps-ne-grid {
                    display: grid;
                    grid-template-columns: 10rem 1fr 14rem;
                    flex: 1;
                    min-height: 0;
                }
                /* When the settings aside is removed (no node selected) the
                   centre canvas reclaims the freed column. */
                .ps-ne-grid:not(:has(.ps-ne-settings)) {
                    grid-template-columns: 10rem 1fr;
                }
                .ps-ne-palette {
                    overflow-y: auto;
                    padding: .55rem .5rem;
                    border-right: 1px solid var(--line, #3A3D40);
                    background: rgba(255,255,255,.02);
                }
                .ps-ne-palette-search {
                    width: 100%;
                    background: var(--surface, #16171a);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .3rem;
                    padding: .3rem .5rem;
                    font: inherit;
                    font-size: .75rem;
                    margin-bottom: .35rem;
                    box-sizing: border-box;
                }
                .ps-ne-palette-search::placeholder { color: var(--ink-dim, #A3A099); }
                .ps-ne-palette-new {
                    width: 100%;
                    background: color-mix(in srgb, var(--accent, #2C66E8) 16%, transparent);
                    color: var(--accent, #2C66E8);
                    border: 1px dashed color-mix(in srgb, var(--accent, #2C66E8) 40%, transparent);
                    border-radius: .3rem;
                    padding: .3rem .5rem;
                    font: inherit;
                    font-size: .7rem;
                    cursor: pointer;
                    margin-bottom: .5rem;
                }
                .ps-ne-palette-new:hover { background: color-mix(in srgb, var(--accent, #2C66E8) 26%, transparent); }
                .ps-ne-palette-row { position: relative; display: block; }
                .ps-ne-palette-row .ps-ne-palette-edit {
                    position: absolute; right: .15rem; top: 50%; transform: translateY(-50%);
                    background: transparent; border: 0; color: var(--ink-dim, #A3A099);
                    cursor: pointer; padding: 0 .3rem; font-size: .7rem;
                }
                .ps-ne-palette-row .ps-ne-palette-edit:hover { color: var(--accent, #2C66E8); }

                .ps-ne-palette h3 {
                    margin: .5rem 0 .3rem;
                    font-size: .65rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-ne-palette-item {
                    display: flex;
                    align-items: center;
                    gap: .45rem;
                    width: 100%;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    color: var(--ink, #F0EDE5);
                    padding: .3rem .5rem;
                    border-radius: .3rem;
                    margin-bottom: .2rem;
                    font: inherit;
                    font-size: .75rem;
                    cursor: pointer;
                    text-align: left;
                }
                .ps-ne-palette-item:hover { background: rgba(255,255,255,.06); }
                .ps-ne-palette-icon { width: 1rem; text-align: center; }

                .ps-ne-canvas-wrap {
                    position: relative;
                    overflow: hidden;
                    background:
                        radial-gradient(circle at 1px 1px, rgba(255,255,255,.06) 1px, transparent 0)
                        var(--surface, #16171a);
                    background-size: 22px 22px;
                }
                .ps-ne-stage {
                    position: absolute;
                    top: 0; left: 0;
                    width: 100%;
                    height: 100%;
                    transform-origin: 0 0;
                    will-change: transform;
                }
                .ps-ne-wires {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    pointer-events: none;
                    overflow: visible;
                }

                .ps-ne-viewport-ctl {
                    position: absolute;
                    top: .5rem;
                    right: .5rem;
                    z-index: 30;
                    display: flex;
                    align-items: center;
                    gap: .15rem;
                    background: rgba(0,0,0,.45);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    padding: .15rem;
                    font: inherit;
                    backdrop-filter: blur(4px);
                }
                .ps-ne-viewport-ctl button {
                    background: transparent;
                    border: 0;
                    color: var(--ink, #F0EDE5);
                    width: 1.6rem;
                    height: 1.6rem;
                    border-radius: .25rem;
                    cursor: pointer;
                    font: inherit;
                }
                .ps-ne-viewport-ctl button:hover { background: rgba(255,255,255,.08); }
                .ps-ne-viewport-zoom {
                    color: var(--ink-dim, #A3A099);
                    font-size: .65rem;
                    font-variant-numeric: tabular-nums;
                    padding: 0 .35rem;
                }
                .ps-ne-wire {
                    fill: none;
                    stroke: var(--accent, #2C66E8);
                    stroke-width: 2;
                    opacity: .85;
                    pointer-events: stroke;
                    cursor: pointer;
                    transition: stroke .15s, opacity .15s;
                    /* Faint flowing dashes show the direction of data travel. */
                    stroke-dasharray: 6 8;
                    animation: ps-ne-wire-flow 1.5s linear infinite;
                }
                .ps-ne-wire:hover { stroke: var(--danger, #ef4444); opacity: 1; stroke-width: 3; }
                @keyframes ps-ne-wire-flow {
                    to { stroke-dashoffset: -14; }
                }

                .ps-ne-empty {
                    position: absolute;
                    inset: 0;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    color: var(--ink-dim, #A3A099);
                    text-align: center;
                    margin: 0;
                    pointer-events: none;
                    padding: 1rem;
                }
                .ps-ne-empty p { margin: .3rem 0; }
                .ps-ne-empty-glyph {
                    font-size: 2.5rem;
                    opacity: .35;
                    margin-bottom: .35rem;
                }
                .ps-ne-empty-hint {
                    font-size: .8rem;
                    max-width: 24rem;
                    line-height: 1.5;
                }
                .ps-pb-nodes-btn {
                    background: rgba(255,255,255,.04);
                    display: inline-flex;
                    align-items: center;
                    gap: .35rem;
                }
                .ps-pb-nodes-btn.is-active {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 22%, transparent);
                    border-color: var(--accent, #2C66E8);
                    color: var(--accent, #2C66E8);
                }
                .ps-pb-nodes-icon { font-size: .95rem; }

                .ps-ne-node {
                    position: absolute;
                    top: 0; left: 0;
                    width: 12rem;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 4px 14px rgba(0,0,0,.35);
                    user-select: none;
                    z-index: 5;
                }
                .ps-ne-node.is-selected {
                    border-color: var(--accent, #2C66E8);
                    box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #2C66E8) 35%, transparent),
                                0 4px 14px rgba(0,0,0,.4);
                }
                .ps-ne-node.is-multi-selected {
                    box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent, #2C66E8) 55%, transparent),
                                0 4px 14px rgba(0,0,0,.4);
                }

                /* Marquee selection rectangle · viewport-local div. */
                .ps-ne-marquee {
                    position: absolute;
                    border: 1px dashed var(--accent, #2C66E8);
                    background: color-mix(in srgb, var(--accent, #2C66E8) 12%, transparent);
                    pointer-events: none;
                    z-index: 25;
                }

                /* Mini-map · always-on overview in the bottom-right corner. */
                .ps-ne-minimap {
                    position: absolute;
                    right: .5rem;
                    bottom: .5rem;
                    width: 9rem;
                    height: 5.4rem;
                    background: rgba(0,0,0,.55);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    overflow: hidden;
                    z-index: 30;
                    cursor: pointer;
                    backdrop-filter: blur(4px);
                }
                .ps-ne-minimap svg { width: 100%; height: 100%; display: block; }
                .ps-ne-node-header {
                    display: flex;
                    align-items: center;
                    gap: .35rem;
                    padding: .25rem .4rem;
                    background: rgba(255,255,255,.04);
                    border-bottom: 1px solid var(--line, #3A3D40);
                    cursor: grab;
                    border-radius: .35rem .35rem 0 0;
                    font-size: .7rem;
                    text-transform: uppercase;
                    letter-spacing: .03em;
                    color: var(--ink-dim, #A3A099);
                }

                /* Group-tinted headers · Blender-style colour-coding so
                   sources, transforms, image ops etc are recognisable from
                   the moment they're visible. */
                .ps-ne-node--group-source     .ps-ne-node-header { background: linear-gradient(180deg, rgba(244,63,94,.32), rgba(244,63,94,.18)); border-bottom-color: rgba(244,63,94,.45); }
                .ps-ne-node--group-transform  .ps-ne-node-header { background: linear-gradient(180deg, rgba(59,130,246,.30), rgba(59,130,246,.16)); border-bottom-color: rgba(59,130,246,.4); }
                .ps-ne-node--group-image      .ps-ne-node-header { background: linear-gradient(180deg, rgba(20,184,166,.30), rgba(20,184,166,.16)); border-bottom-color: rgba(20,184,166,.4); }
                .ps-ne-node--group-output     .ps-ne-node-header { background: linear-gradient(180deg, rgba(34,197,94,.30), rgba(34,197,94,.16)); border-bottom-color: rgba(34,197,94,.4); }

                .ps-ne-node-action {
                    background: transparent;
                    border: 0;
                    color: var(--ink-dim, #A3A099);
                    padding: 0 .25rem;
                    font: inherit;
                    font-size: .7rem;
                    cursor: pointer;
                    border-radius: .2rem;
                }
                .ps-ne-node-action:hover { color: var(--ink, #F0EDE5); background: rgba(255,255,255,.08); }
                .ps-ne-node-action.is-on {
                    background: var(--accent, #2C66E8);
                    color: #fff;
                }

                /* Muted node · whole node fades, sockets keep colour so
                   wires remain legible. */
                .ps-ne-node.is-muted { opacity: .55; }
                .ps-ne-node.is-muted .ps-ne-node-header { filter: saturate(.3); }
                .ps-ne-node-header:active { cursor: grabbing; }
                .ps-ne-node-icon { width: 1rem; text-align: center; color: var(--ink, #F0EDE5); }
                .ps-ne-node-label { color: var(--ink, #F0EDE5); }
                .ps-ne-node-remove {
                    margin-left: auto;
                    background: transparent;
                    border: 0;
                    color: var(--ink-dim, #A3A099);
                    font-size: .85rem;
                    cursor: pointer;
                    padding: 0 .25rem;
                }
                .ps-ne-node-remove:hover { color: var(--danger, #ef4444); }

                .ps-ne-node-body {
                    padding: .35rem 0;
                    font-size: .75rem;
                }
                .ps-ne-socket-row {
                    display: flex;
                    align-items: center;
                    gap: .35rem;
                    padding: .15rem 0;
                }
                .ps-ne-socket-row--in  { padding-left: 0; padding-right: .55rem; }
                .ps-ne-socket-row--out { padding-left: .55rem; padding-right: 0; justify-content: flex-end; }
                .ps-ne-socket {
                    width: .65rem;
                    height: .65rem;
                    border-radius: 50%;
                    background: var(--surface, #16171a);
                    border: 2px solid var(--accent, #2C66E8);
                    cursor: pointer;
                    padding: 0;
                    flex-shrink: 0;
                }
                .ps-ne-socket--in  { margin-left: -.35rem; }
                .ps-ne-socket--out { margin-right: -.35rem; }
                .ps-ne-socket:hover { background: var(--accent, #2C66E8); }
                .ps-ne-socket.is-pending {
                    background: var(--accent, #2C66E8);
                    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent, #2C66E8) 40%, transparent);
                }
                .ps-ne-socket-label {
                    color: var(--ink, #F0EDE5);
                    flex: 1;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                /* Live socket value · sits under the output row showing the
                   current evaluated result against sample route variables. */
                .ps-ne-live-value {
                    font-family: ui-monospace, monospace;
                    font-size: .65rem;
                    color: var(--ink-dim, #A3A099);
                    background: rgba(255,255,255,.03);
                    border-left: 2px solid color-mix(in srgb, var(--accent, #2C66E8) 30%, transparent);
                    padding: .1rem .45rem;
                    margin: 0 .35rem .15rem;
                    border-radius: 0 .2rem .2rem 0;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                /* Image thumbnail under an output socket · shows the live
                   CSS-filter chain on the source URL, so the user sees the
                   pipeline render exactly as it would on the page. */
                .ps-ne-image-preview {
                    position: relative;
                    margin: .25rem .35rem .35rem;
                    border-radius: .25rem;
                    overflow: hidden;
                    background: rgba(255,255,255,.03);
                    border: 1px solid var(--line, #3A3D40);
                }
                .ps-ne-image-preview img {
                    display: block;
                    width: 100%;
                    height: auto;
                    max-height: 9rem;
                    object-fit: cover;
                    background:
                        linear-gradient(45deg, #2a2b30 25%, transparent 25%),
                        linear-gradient(-45deg, #2a2b30 25%, transparent 25%),
                        linear-gradient(45deg, transparent 75%, #2a2b30 75%),
                        linear-gradient(-45deg, transparent 75%, #2a2b30 75%);
                    background-size: 12px 12px;
                    background-position: 0 0, 0 6px, 6px -6px, -6px 0;
                }
                .ps-ne-image-preview-fail {
                    display: none;
                    padding: .85rem .5rem;
                    font-size: .7rem;
                    color: var(--ink-dim, #A3A099);
                    text-align: center;
                    font-style: italic;
                }
                .ps-ne-image-filter {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: rgba(0,0,0,.55);
                    color: #5eead4;
                    font-family: ui-monospace, monospace;
                    font-size: .6rem;
                    padding: .15rem .35rem;
                    text-align: center;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                /* Sticky-note node · yellow paper look, no socket rows. */
                .ps-ne-node--note {
                    width: 14rem;
                    background: #fde68a;
                    color: #422006;
                    border-color: #d97706;
                }
                .ps-ne-node--note .ps-ne-node-header {
                    background: rgba(0,0,0,.06);
                    color: #422006;
                    border-bottom-color: rgba(0,0,0,.1);
                }
                .ps-ne-node--note .ps-ne-node-icon,
                .ps-ne-node--note .ps-ne-node-label { color: #422006; }
                .ps-ne-node--note .ps-ne-node-remove { color: #92400e; }
                .ps-ne-node-note {
                    padding: .55rem .65rem;
                    font-size: .8rem;
                    line-height: 1.4;
                    white-space: pre-wrap;
                }

                /* ─── Socket / wire type colours ────────────────────────── */
                /* Default (any) keeps the accent palette.                   */
                .ps-ne-socket--type-string     { border-color: #3b82f6; }
                .ps-ne-socket--type-string:hover,
                .ps-ne-socket--type-string.is-pending { background: #3b82f6; }
                .ps-ne-socket--type-int        { border-color: #22c55e; }
                .ps-ne-socket--type-int:hover,
                .ps-ne-socket--type-int.is-pending    { background: #22c55e; }
                .ps-ne-socket--type-bool       { border-color: #a855f7; }
                .ps-ne-socket--type-bool:hover,
                .ps-ne-socket--type-bool.is-pending   { background: #a855f7; }
                .ps-ne-socket--type-array      { border-color: #f59e0b; }
                .ps-ne-socket--type-array:hover,
                .ps-ne-socket--type-array.is-pending  { background: #f59e0b; }
                .ps-ne-socket--type-object,
                .ps-ne-socket--type-model      { border-color: #ec4899; }
                .ps-ne-socket--type-object:hover,
                .ps-ne-socket--type-model:hover,
                .ps-ne-socket--type-object.is-pending,
                .ps-ne-socket--type-model.is-pending  { background: #ec4899; }
                .ps-ne-socket--type-collection { border-color: #f97316; }
                .ps-ne-socket--type-collection:hover,
                .ps-ne-socket--type-collection.is-pending { background: #f97316; }
                .ps-ne-socket--type-image      { border-color: #14b8a6; }
                .ps-ne-socket--type-image:hover,
                .ps-ne-socket--type-image.is-pending  { background: #14b8a6; }
                .ps-ne-socket--type-any        { border-color: #94a3b8; }
                .ps-ne-socket--type-any:hover,
                .ps-ne-socket--type-any.is-pending    { background: #94a3b8; }

                .ps-ne-type-pill {
                    font-size: .55rem;
                    text-transform: uppercase;
                    letter-spacing: .04em;
                    padding: 0 .35rem;
                    border-radius: .2rem;
                    font-family: ui-monospace, monospace;
                    flex-shrink: 0;
                    background: rgba(255,255,255,.05);
                    color: var(--ink-dim, #A3A099);
                }
                .ps-ne-type-pill--string     { background: rgba(59,130,246,.18);  color: #93c5fd; }
                .ps-ne-type-pill--int        { background: rgba(34,197,94,.18);   color: #86efac; }
                .ps-ne-type-pill--bool       { background: rgba(168,85,247,.18);  color: #d8b4fe; }
                .ps-ne-type-pill--array      { background: rgba(245,158,11,.18);  color: #fcd34d; }
                .ps-ne-type-pill--object,
                .ps-ne-type-pill--model      { background: rgba(236,72,153,.18);  color: #f9a8d4; }
                .ps-ne-type-pill--collection { background: rgba(249,115,22,.18);  color: #fdba74; }
                .ps-ne-type-pill--image      { background: rgba(20,184,166,.18);  color: #5eead4; }
                .ps-ne-type-pill--any        { background: rgba(148,163,184,.18); color: #cbd5e1; }

                /* Wire colour follows the source socket's type. */
                .ps-ne-wire                            { stroke: #94a3b8; }
                .ps-ne-wire[data-wire-type="string"]     { stroke: #3b82f6; }
                .ps-ne-wire[data-wire-type="int"]        { stroke: #22c55e; }
                .ps-ne-wire[data-wire-type="bool"]       { stroke: #a855f7; }
                .ps-ne-wire[data-wire-type="array"]      { stroke: #f59e0b; }
                .ps-ne-wire[data-wire-type="object"],
                .ps-ne-wire[data-wire-type="model"]      { stroke: #ec4899; }
                .ps-ne-wire[data-wire-type="collection"] { stroke: #f97316; }
                .ps-ne-wire[data-wire-type="image"]      { stroke: #14b8a6; }
                .ps-ne-wire[data-wire-type="any"]        { stroke: #94a3b8; }
                /* Type-mismatch warning: amber dashed line overrides the
                   normal tint so the issue is unmissable. */
                .ps-ne-wire[data-wire-warn="1"] {
                    stroke: #f59e0b !important;
                    stroke-dasharray: 6 4;
                    stroke-width: 2.5;
                }
                /* Drag-to-connect ghost wire · dashed white that follows the
                   cursor while wiring two sockets. */
                .ps-ne-wire--ghost {
                    pointer-events: none;
                    stroke-dasharray: 4 4;
                    opacity: .9;
                    stroke-width: 2;
                }

                .ps-ne-ctx-menu {
                    position: absolute;
                    z-index: 60;
                    min-width: 14rem;
                    max-width: 18rem;
                    max-height: 22rem;
                    overflow-y: auto;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 10px 28px rgba(0,0,0,.5);
                    padding: .2rem;
                }
                .ps-ne-ctx-section {
                    font-size: .6rem;
                    text-transform: uppercase;
                    letter-spacing: .07em;
                    color: var(--ink-dim, #A3A099);
                    padding: .45rem .55rem .2rem;
                }
                .ps-ne-ctx-item {
                    display: flex;
                    align-items: center;
                    gap: .45rem;
                    width: 100%;
                    background: transparent;
                    border: 0;
                    color: var(--ink, #F0EDE5);
                    text-align: left;
                    padding: .3rem .55rem;
                    border-radius: .25rem;
                    font: inherit;
                    font-size: .8rem;
                    cursor: pointer;
                }
                .ps-ne-ctx-item:hover {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 18%, transparent);
                }
                .ps-ne-ctx-item code {
                    color: var(--accent, #2C66E8);
                    font-family: ui-monospace, monospace;
                    font-size: .75rem;
                }
                .ps-ne-ctx-preview {
                    color: var(--ink-dim, #A3A099);
                    font-size: .7rem;
                    font-family: ui-monospace, monospace;
                    margin-left: auto;
                }
                .ps-ne-ctx-icon { width: 1rem; text-align: center; }

                /* Block-tree right-click context menu · fixed positioning
                   so the menu sits above any scrolling canvas frame. */
                .ps-pb-block-ctx-menu {
                    position: fixed;
                    z-index: 80;
                    min-width: 14rem;
                    max-width: 20rem;
                    max-height: 24rem;
                    overflow-y: auto;
                    background: var(--surface-2, #1E1F22);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .35rem;
                    box-shadow: 0 10px 28px rgba(0,0,0,.5);
                    padding: .2rem;
                }
                .ps-pb-block-ctx-section {
                    font-size: .6rem;
                    text-transform: uppercase;
                    letter-spacing: .07em;
                    color: var(--ink-dim, #A3A099);
                    padding: .45rem .55rem .2rem;
                }
                .ps-pb-block-ctx-item {
                    display: flex;
                    align-items: center;
                    gap: .45rem;
                    width: 100%;
                    background: transparent;
                    border: 0;
                    color: var(--ink, #F0EDE5);
                    text-align: left;
                    padding: .3rem .55rem;
                    border-radius: .25rem;
                    font: inherit;
                    font-size: .8rem;
                    cursor: pointer;
                }
                .ps-pb-block-ctx-item:hover {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 18%, transparent);
                }
                .ps-pb-block-ctx-icon { width: 1rem; text-align: center; }
                .ps-pb-block-ctx-danger { color: #F87171; }
                .ps-pb-block-ctx-danger:hover {
                    background: rgba(248,113,113,.16);
                }

                /* Search-and-replace overlay · uses the finder shell with
                   side-by-side find / replace fields. */
                .ps-pb-replace-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: .6rem;
                    padding: .6rem;
                }
                .ps-pb-replace-field {
                    display: flex;
                    flex-direction: column;
                    gap: .25rem;
                }
                .ps-pb-replace-field span {
                    font-size: .65rem;
                    text-transform: uppercase;
                    letter-spacing: .07em;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-pb-replace-field input {
                    background: var(--surface, #141518);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .3rem;
                    color: var(--ink, #F0EDE5);
                    padding: .35rem .5rem;
                    font: inherit;
                    font-size: .85rem;
                }
                .ps-pb-replace-controls {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0 .6rem .6rem;
                    gap: .6rem;
                }
                .ps-pb-replace-actions {
                    display: flex;
                    gap: .4rem;
                }

                .ps-ne-settings {
                    overflow-y: auto;
                    padding: .65rem .65rem;
                    border-left: 1px solid var(--line, #3A3D40);
                    background: rgba(255,255,255,.02);
                }
                .ps-ne-settings h3 {
                    margin: 0 0 .5rem;
                    font-size: .65rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    color: var(--ink-dim, #A3A099);
                }

                /* ─── Collaboration · block locks, presence, activity feed ─── */
                .ps-pb-block-wrap.is-locked {
                    /* Dim a locked block enough that it reads as "claimed"
                       without hiding the content · collaborators still need
                       to see the in-flight edits. */
                    opacity: .55;
                    pointer-events: none;
                    position: relative;
                }
                .ps-pb-lock-ribbon {
                    position: absolute;
                    top: 0;
                    left: 0;
                    z-index: 5;
                    background: var(--danger, #ef4444);
                    color: #fff;
                    font-size: .7rem;
                    padding: .12rem .4rem;
                    border-bottom-right-radius: .25rem;
                    font-weight: 600;
                    letter-spacing: .02em;
                }
                .ps-pb-presence {
                    display: inline-flex;
                    align-items: center;
                    gap: .25rem;
                    font-size: .7rem;
                    color: var(--ink-dim, #A3A099);
                }
                .ps-pb-presence-label {
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    font-size: .6rem;
                }
                .ps-pb-presence-chip {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 1.5rem;
                    height: 1.5rem;
                    border-radius: 50%;
                    background: var(--accent, #2C66E8);
                    color: #fff;
                    font-size: .65rem;
                    font-weight: 600;
                    letter-spacing: .02em;
                }
                .ps-pb-activity-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                    display: flex;
                    flex-direction: column;
                    gap: .35rem;
                }
                .ps-pb-activity-row {
                    display: flex;
                    gap: .4rem;
                    align-items: flex-start;
                    font-size: .75rem;
                    color: var(--ink, #F0EDE5);
                    padding: .25rem .35rem;
                    border-radius: .25rem;
                    background: rgba(255,255,255,.02);
                }
                .ps-pb-activity-icon {
                    flex: none;
                    width: 1rem;
                    text-align: center;
                }
                .ps-pb-activity-when {
                    color: var(--ink-dim, #A3A099);
                    font-size: .65rem;
                    margin-left: auto;
                    white-space: nowrap;
                }
                .ps-pb-rail-tab-strip {
                    display: flex;
                    gap: .25rem;
                    margin-bottom: .5rem;
                    border-bottom: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-rail-tab {
                    background: transparent;
                    border: 0;
                    color: var(--ink-dim, #A3A099);
                    padding: .3rem .55rem;
                    font-size: .7rem;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                }
                .ps-pb-rail-tab.is-active {
                    color: var(--ink, #F0EDE5);
                    border-bottom-color: var(--accent, #2C66E8);
                }
            </style>
        @endpush
    @endonce
</div>
