<div
    x-data="pageStudioPageBuilder()"
    @page-studio:page:saved.window="showToast('Page saved', true)"
    @page-studio:graph:saved.window="showToast('Graph autosaved', true)"
    @page-studio:graph:copied.window="showToast('Copied ' + ($event.detail.count || 0) + ' nodes', true)"
    @page-studio:replace:done.window="showToast(($event.detail.count || 0) + ' blocks updated', true)"
    @page-studio:lock:denied.window="showToast('Block is being edited by ' + ($event.detail.holder || 'someone else'), false)"
    @keydown.window.alt.up.prevent="if ($wire.selectedPath) $wire.moveSelectedBlock(-1)"
    @keydown.window.alt.down.prevent="if ($wire.selectedPath) $wire.moveSelectedBlock(1)"
    class="ps-page-builder"
    x-init="
        // Keep --ps-pb-drawer-h in sync with the Livewire drawerOpen
        // prop · x-effect on a $wire getter doesn't always re-run when
        // the Livewire snapshot changes, so use an explicit $watch.
        const syncDrawerH = () => {
            const h = $wire.drawerOpen ? (parseInt(localStorage.getItem('psPbDrawerH') || '352')) : 0;
            document.documentElement.style.setProperty('--ps-pb-drawer-h', h + 'px');
        };
        syncDrawerH();
        $watch(() => $wire.drawerOpen, syncDrawerH);

        $watch('leftRailW',    v => localStorage.setItem('psPbLeftRailW',    String(v)));
        $watch('rightRailW',   v => localStorage.setItem('psPbRightRailW',   String(v)));
        $watch('neLeftRailW',  v => localStorage.setItem('psPbNeLeftRailW',  String(v)));
        $watch('neRightRailW', v => localStorage.setItem('psPbNeRightRailW', String(v)));
    "
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

            {{-- The Show/Hide nodes button moved to the floating tuck
                 handle at the bottom of the viewport · always visible
                 without scrolling, regardless of canvas length. --}}

            <div class="ps-pb-device-toggle" role="group" aria-label="Preview device frame">
                <button type="button"
                        @click="device = 'phone'; localStorage.setItem('psPbDevice', 'phone')"
                        :class="device === 'phone' ? 'is-active' : ''"
                        class="ps-pb-device-btn"
                        :aria-pressed="device === 'phone' ? 'true' : 'false'"
                        title="Phone (~390px)">📱 Phone</button>
                <button type="button"
                        @click="device = 'tablet'; localStorage.setItem('psPbDevice', 'tablet')"
                        :class="device === 'tablet' ? 'is-active' : ''"
                        class="ps-pb-device-btn"
                        :aria-pressed="device === 'tablet' ? 'true' : 'false'"
                        title="Tablet (~768px)">▭ Tablet</button>
                <button type="button"
                        @click="device = 'desktop'; localStorage.setItem('psPbDevice', 'desktop')"
                        :class="device === 'desktop' ? 'is-active' : ''"
                        class="ps-pb-device-btn"
                        :aria-pressed="device === 'desktop' ? 'true' : 'false'"
                        title="Desktop">🖥 Desktop</button>
            </div>

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

            {{-- The scheduled-publish datetime-local picker was dropped
                 from the topbar · authors who need scheduled publishing
                 can set page->publish_at programmatically. Keeping the
                 publishAt server property intact for backward compat. --}}

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
        {{-- Preview pane · reads `device` straight from the page-builder
             root scope so the topbar's Phone / Tablet / Desktop buttons
             drive both the edit-canvas frame and the preview pane. The
             previous in-preview toolbar had its own Alpine scope that
             didn't sync with the topbar · removed for one source of
             truth. --}}
        <div class="ps-pb-preview-wrap">
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
                + ((rightCollapsed || ! $wire.selectedPath) ? 'is-right-collapsed' : '')"
             {{-- The grid reserves bottom padding for the drawer + the
                  var-strip. When the drawer is closed we want ONLY the
                  ~52px var-strip reserve · branch on $wire.drawerOpen
                  the same way the var-strip does so a stale
                  --ps-pb-drawer-h CSS variable can't leave a phantom
                  ~480px gap that prevents the left rail + canvas from
                  filling the available height. --}}
             :style="`--rail-l: ${leftRailW}px; --rail-r: ${rightRailW}px;`
                    + ($wire.drawerOpen
                        ? `padding-bottom: calc(var(--ps-pb-drawer-h, 0px) + 3.25rem);`
                        : `padding-bottom: 3.25rem;`)">

            {{-- ─── LEFT · components grouped + variables panel ─── --}}
            <aside class="ps-pb-rail ps-pb-rail--left"
                   x-show="! leftCollapsed" x-cloak>
                <button type="button"
                        class="ps-pb-rail-grabber ps-pb-rail-grabber--right"
                        @pointerdown="startRailResize($event, 'left')"
                        aria-label="Resize components rail"
                        title="Drag to resize"></button>
                @foreach ($this->blockLibrary as $group => $items)
                    <section class="ps-pb-section">
                        <h3>{{ ucfirst($group) }}</h3>
                        <div class="ps-pb-palette">
                            @foreach ($items as $type => $def)
                                <button type="button"
                                        class="ps-pb-palette-item"
                                        draggable="true"
                                        @dragstart.stop="onPaletteDragStart($event, @js($type)); closeRailOnMobile()"
                                        @pointerdown="startTouchDrag($event, 'palette', @js($type), @js($def['label']))"
                                        @click="$wire.addBlock(@js($type)); closeRailOnMobile()"
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
                                            @dragstart.stop="onSnippetDragStart($event, @js($s['name'])); closeRailOnMobile()"
                                            @pointerdown="startTouchDrag($event, 'snippet', @js($s['name']), @js($s['label']))"
                                            @click="$wire.dropSnippet(@js($s['name'])); closeRailOnMobile()"
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

                {{-- Variables · the left-rail panel used to list every
                     page variable as a draggable chip, but the
                     persistent .ps-pb-var-strip at the bottom of the
                     viewport (always visible · scrollable on phone)
                     covers the same job in a more reachable spot.
                     The strip's chips share the drag/drop behaviour
                     and the right-click→insert-as-node path, so this
                     section was pure duplication · removed. --}}
            </aside>

            {{-- ─── CENTRE · canvas ─── --}}
            <main class="ps-pb-canvas-wrap" :class="'ps-pb-canvas-wrap--' + device">
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
                   x-show="! rightCollapsed && ($wire.selectedPath || ['comments','activity','seo','a11y'].includes(rightTab))" x-cloak>
                <button type="button"
                        class="ps-pb-rail-grabber ps-pb-rail-grabber--left"
                        @pointerdown="startRailResize($event, 'right')"
                        aria-label="Resize settings rail"
                        title="Drag to resize"></button>

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
                    <button type="button"
                            class="ps-pb-rail-tab"
                            :class="rightTab === 'seo' ? 'is-active' : ''"
                            @click="rightTab = 'seo'">SEO</button>
                    <button type="button"
                            class="ps-pb-rail-tab"
                            :class="rightTab === 'a11y' ? 'is-active' : ''"
                            @click="rightTab = 'a11y'">A11y</button>
                </div>

                <section class="ps-pb-section" x-show="rightTab === 'comments'" x-cloak>
                    @include('page-studio::livewire._comments-panel')
                </section>

                <section class="ps-pb-section" x-show="rightTab === 'seo'" x-cloak>
                    <h3>SEO</h3>
                    <p style="color: var(--ink-dim, #A3A099); font-size: .85rem; margin: 0 0 .75rem;">
                        Stored in the page <code>meta</code> blob. Render them in your host app's layout
                        (<code>&lt;title&gt;</code>, <code>&lt;meta name="description"&gt;</code>,
                        <code>&lt;meta property="og:image"&gt;</code>).
                    </p>
                    <label class="ps-pb-meta-field">
                        <span>Title</span>
                        <input type="text"
                               wire:model.live.debounce.400ms="meta.seo_title"
                               placeholder="Page title shown in the browser tab + search results"
                               class="ps-pb-meta-input">
                    </label>
                    <label class="ps-pb-meta-field">
                        <span>Description</span>
                        <textarea wire:model.live.debounce.400ms="meta.seo_description"
                                  placeholder="One- or two-sentence description for search snippets + social previews"
                                  class="ps-pb-meta-input" rows="3"></textarea>
                    </label>
                    <label class="ps-pb-meta-field">
                        <span>OG image URL</span>
                        <input type="url"
                               wire:model.live.debounce.400ms="meta.og_image"
                               placeholder="https://example.com/social-card.png"
                               class="ps-pb-meta-input">
                    </label>
                </section>

                <section class="ps-pb-section" x-show="rightTab === 'a11y'" x-cloak>
                    <h3>Accessibility</h3>
                    <button type="button" wire:click="runA11yScan" class="ps-pb-btn">Run accessibility scan</button>
                    @php $findings = $this->a11yFindings ?? null; @endphp
                    @if ($findings === null)
                        <p style="color: var(--ink-dim, #A3A099); font-size: .85rem; margin-top: .75rem;">
                            Click <strong>Run accessibility scan</strong> to check images for missing alt text + the
                            heading-level outline for skipped levels.
                        </p>
                    @elseif (empty($findings))
                        <p style="color: #22c55e; font-size: .85rem; margin-top: .75rem;">
                            ✓ No accessibility issues found.
                        </p>
                    @else
                        <ul style="list-style: none; padding: 0; margin: .75rem 0 0; font-size: .85rem;">
                            @foreach ($findings as $f)
                                <li style="padding: .35rem 0; border-bottom: 1px solid var(--line, #3A3D40);">
                                    <strong style="color: #f97316;">{{ $f['kind'] }}</strong> ·
                                    <span style="color: var(--ink-dim, #A3A099);">{{ $f['message'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
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
                            {{-- wire:key keyed on the selected block path so
                                 Livewire force-remounts inputs when the user
                                 switches between two blocks of the same type
                                 (e.g. after duplicateBlock). Without it the
                                 morphed input could keep the previous
                                 wire:model path and route typed values to
                                 the wrong block. --}}
                            <div class="ps-pb-field" data-field-key="{{ $key }}"
                                 wire:key="block-field-{{ $this->selectedPath }}-{{ $key }}">
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
                                        @focus="$wire.setEditingField(@js($block['id']), @js($def['label'] ?? $key))"
                                        @blur="$wire.clearEditingField(@js($block['id']))"
                                        @dragover.prevent="onDropZoneOver($event)"
                                        @dragleave="$event.currentTarget.removeAttribute('data-ps-var-drop')"
                                        @drop.prevent="onDropIntoField($event)"
                                        @contextmenu.prevent="openVarPicker($event)"
                                    ></textarea>
                                @elseif ($def['kind'] === 'select')
                                    <select wire:model.live="{{ $prefix.$key }}"
                                            @focus="$wire.setEditingField(@js($block['id']), @js($def['label'] ?? $key))"
                                            @blur="$wire.clearEditingField(@js($block['id']))">
                                        @foreach ($def['options'] ?? [] as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        type="{{ $def['kind'] === 'url' ? 'url' : ($def['kind'] === 'number' ? 'number' : 'text') }}"
                                        data-wire-prop="{{ $prefix.$key }}"
                                        wire:model.live.debounce.300ms="{{ $prefix.$key }}"
                                        @focus="$wire.setEditingField(@js($block['id']), @js($def['label'] ?? $key))"
                                        @blur="$wire.clearEditingField(@js($block['id']))"
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

    {{-- ─── Variables strip ───────────────────────────────────────────────────
         A horizontal scrollable marquee of variable chips that sits just
         above the Variables Modifier drawer. Always addressable regardless
         of canvas scroll. Each chip is a draggable {{ name }} token · drop
         it into any text input to insert the variable substitution. --}}
    @if (! $previewMode && ! empty($this->variables))
        <div class="ps-pb-var-strip"
             role="toolbar"
             aria-label="Page variables"
             :style="$wire.drawerOpen
                        ? `bottom: calc(var(--ps-pb-drawer-h, 0px) + 0px)`
                        : `bottom: 8px`">
            <span class="ps-pb-var-strip-label">Variables</span>
            <div class="ps-pb-var-strip-track">
                @foreach ($this->variables as $v)
                    @php $token = '{{ '.$v['name'].' }}'; @endphp
                    <button type="button"
                            class="ps-pb-var-chip"
                            data-var-name="{{ $v['name'] }}"
                            draggable="true"
                            @dragstart.stop="
                                $event.dataTransfer.setData('text/plain', @js($token));
                                $event.dataTransfer.setData('application/x-page-studio-var', @js($v['name']));
                                $event.dataTransfer.effectAllowed = 'copy';
                            "
                            @click="navigator.clipboard?.writeText(@js($token)); showToast('Copied ' + @js($token) + ' to clipboard', true)"
                            :title="@js('Drag into a text field or click to copy '.$token)">
                        <span class="ps-pb-var-chip-tok">&#123;&#123;</span>
                        <span class="ps-pb-var-chip-name">{{ $v['name'] }}</span>
                        <span class="ps-pb-var-chip-tok">&#125;&#125;</span>
                        @if (! empty($v['preview']))
                            <span class="ps-pb-var-chip-preview">·&nbsp;{{ \Illuminate\Support\Str::limit((string) $v['preview'], 18) }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ─── Bottom drawer · node variable editor ───────────────────────────── --}}
    {{-- Tuck handle · always-visible bottom-pill that opens / closes the
         node drawer. Mirrors the logged-cloud/navigation chrome's tuck
         pattern: one persistent affordance, no scrolling needed to find
         it on a tall canvas. Hidden in preview mode where the editor
         chrome itself disappears. --}}
    @if (! $previewMode && $this->nodeEditorEnabled())
        <button type="button"
                class="ps-ne-tuck-handle"
                wire:click="toggleDrawer"
                :class="$wire.drawerOpen ? 'is-open' : ''"
                :aria-expanded="$wire.drawerOpen ? 'true' : 'false'"
                aria-controls="ps-ne-drawer-region"
                :aria-label="$wire.drawerOpen ? 'Hide variables modifier' : 'Show variables modifier'"
                :title="$wire.drawerOpen ? 'Tuck variables modifier' : 'Show variables modifier'">
            <span class="ps-ne-tuck-grip" aria-hidden="true"></span>
            <span class="ps-ne-tuck-label">⌘ Variables Modifier</span>
        </button>
    @endif

    @if ($drawerOpen && ! $previewMode)
        <section class="ps-ne-drawer"
                 id="ps-ne-drawer-region"
                 data-component="page-studio.node-editor"
                 x-data="{ height: parseInt(localStorage.getItem('psPbDrawerH') || '352') }"
                 x-init="
                    // Sync the var on mount so a freshly-opened drawer
                    // already reserves grid space.
                    document.documentElement.style.setProperty('--ps-pb-drawer-h', height + 'px');
                    $watch('height', v => {
                        localStorage.setItem('psPbDrawerH', String(v));
                        document.documentElement.style.setProperty('--ps-pb-drawer-h', v + 'px');
                    });
                 "
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
                <button type="button"
                        class="ps-pb-btn ps-ne-palette-toggle"
                        @click="nodePaletteCollapsed = ! nodePaletteCollapsed"
                        :aria-pressed="nodePaletteCollapsed ? 'false' : 'true'"
                        :aria-label="nodePaletteCollapsed ? 'Show node palette' : 'Hide node palette'"
                        :title="nodePaletteCollapsed ? 'Show palette' : 'Hide palette'">
                    <span x-show="! nodePaletteCollapsed" aria-hidden="true">◀ Palette</span>
                    <span x-show="nodePaletteCollapsed" x-cloak aria-hidden="true">▶ Palette</span>
                </button>
                <span class="ps-ne-title">Variables Modifier</span>
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

            <div class="ps-ne-grid"
                 :class="{
                    'ps-ne-grid--palette-closed': nodePaletteCollapsed,
                 }"
                 :style="`--ne-rail-l: ${neLeftRailW}px; --ne-rail-r: ${neRightRailW}px`">
                {{-- ─── LEFT · palette ──────────────────────────────────── --}}
                <aside class="ps-ne-palette"
                       x-data="{ query: '' }"
                       x-show="! nodePaletteCollapsed"
                       x-cloak>
                    <button type="button"
                            class="ps-ne-rail-grabber ps-ne-rail-grabber--right"
                            @pointerdown="startRailResize($event, 'neLeft')"
                            aria-label="Resize node palette"
                            title="Drag to resize"></button>
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
                                            @click="closeNodePaletteOnMobile()"
                                            @dragstart="
                                                $event.dataTransfer.setData('text/plain', 'ps-ne-palette:' + @js($key));
                                                $event.dataTransfer.effectAllowed = 'copy';
                                                closeNodePaletteOnMobile();
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
                            // Class-defined nodes can override settings dynamically based on the
                            // current instance · e.g. Model finder's finder_key becomes a select
                            // sourced from the selected model's findBy. Merge per-field so the
                            // rest of the static schema stays intact.
                            if (! empty($schema['class']) && class_exists($schema['class'])) {
                                try {
                                    $nodeInstance = new ($schema['class'])();
                                    if (method_exists($nodeInstance, 'dynamicSettings')) {
                                        $dynamic = $nodeInstance->dynamicSettings($node);
                                        if (is_array($dynamic) && ! empty($schema['settings'])) {
                                            foreach ($dynamic as $dKey => $dDef) {
                                                $schema['settings'][$dKey] = array_merge(
                                                    $schema['settings'][$dKey] ?? [],
                                                    $dDef,
                                                );
                                            }
                                        }
                                    }
                                } catch (\Throwable $_) {}
                            }
                            $isNote = ($schema['group'] ?? '') === 'note';
                            $liveOutputs = ($this->nodeSocketValues)[$node['id']] ?? [];
                            // Skip duplicate input rows for fields that also exist as
                            // settings · the settings section now carries the wireable pip
                            // for those, so showing one in inputs too is a confusing
                            // double-pip on the same socket name.
                            $settingsKeys = array_keys($schema['settings'] ?? []);
                            $renderedInputs = collect($schema['inputs'] ?? [])
                                ->reject(fn ($_, $key) => in_array($key, $settingsKeys, true))
                                ->all();
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
                                    {{-- Inputs · click to complete a pending connection.
                                         Fields that also exist in settings are rendered as
                                         pips in the settings section below instead, so a
                                         socket name only ever has ONE visual pip. --}}
                                    @foreach ($renderedInputs as $key => $entry)
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

                                    {{-- ─── Settings rows (Blender-style) ───
                                         Each settable field renders directly on
                                         the node card. A socket pip on the left
                                         lets authors PROMOTE the field to a
                                         wired input · when wired, the static
                                         control is disabled and the engine
                                         routes the wired value into evaluate()
                                         via the settings-as-implicit-inputs
                                         merge. --}}
                                    @if (! empty($schema['settings']))
                                        @foreach ($schema['settings'] as $sKey => $sDef)
                                            @php
                                                $kind = $sDef['kind'] ?? 'text';
                                                // Every setting carries a wireable pip · selects and
                                                // uploads included. When the row is wired the static
                                                // control still renders but is dimmed; the engine
                                                // merges the wired value into evaluate() either way.
                                                $sockType   = match (true) {
                                                    in_array($kind, ['number'], true)  => 'int',
                                                    in_array($kind, ['bool'], true)    => 'bool',
                                                    in_array($kind, ['color'], true)   => 'color',
                                                    default                            => 'string',
                                                };
                                                $modelPath = "nodes.$i.settings.$sKey";
                                                $wireExpr  = "(\$wire.edges || []).some(e => e.to_node === '".$node['id']."' && e.to_socket === '".$sKey."')";
                                            @endphp
                                            <div class="ps-ne-setting-row"
                                                 wire:key="ne-setting-{{ $node['id'] }}-{{ $sKey }}"
                                                 :class="{{ $wireExpr }} ? 'is-wired' : ''">
                                                <button type="button"
                                                        class="ps-ne-socket ps-ne-socket--in ps-ne-socket--type-{{ $sockType }}"
                                                        data-socket-node="{{ $node['id'] }}"
                                                        data-socket-key="{{ $sKey }}"
                                                        data-socket-kind="in"
                                                        data-socket-type="{{ $sockType }}"
                                                        wire:click.stop="completeConnection(@js($node['id']), @js($sKey))"
                                                        title="{{ $sDef['label'] ?? $sKey }} · wire-in to override"></button>

                                                <label class="ps-ne-setting-label">{{ $sDef['label'] ?? $sKey }}</label>

                                                <span class="ps-ne-setting-control" :style="{{ $wireExpr }} ? 'opacity:.4;pointer-events:none' : ''">
                                                    @if ($kind === 'select')
                                                        <select wire:model.live="{{ $modelPath }}">
                                                            @foreach ($sDef['options'] ?? [] as $val => $lbl)
                                                                <option value="{{ $val }}">{{ $lbl }}</option>
                                                            @endforeach
                                                        </select>
                                                    @elseif ($kind === 'number')
                                                        <input type="number" step="any"
                                                               wire:model.live.debounce.300ms="{{ $modelPath }}">
                                                    @elseif ($kind === 'bool')
                                                        <input type="checkbox" wire:model.live="{{ $modelPath }}">
                                                    @elseif ($kind === 'color')
                                                        <input type="color" wire:model.live="{{ $modelPath }}">
                                                    @elseif ($kind === 'upload')
                                                        @php $val = data_get($this, $modelPath); @endphp
                                                        @if (! empty($val))
                                                            <button type="button" class="ps-pb-btn"
                                                                    wire:click="clearUpload(@js($modelPath))">Replace</button>
                                                        @else
                                                            <input type="file" accept="image/*"
                                                                   @click="$wire.set('uploadTargetProp', @js($modelPath))"
                                                                   wire:model="uploadFile">
                                                        @endif
                                                    @elseif ($kind === 'textarea')
                                                        <textarea rows="2"
                                                                  wire:model.live.debounce.300ms="{{ $modelPath }}"></textarea>
                                                    @else
                                                        <input type="text"
                                                               wire:model.live.debounce.300ms="{{ $modelPath }}">
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
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

                    {{-- ─── Right-click weapon-wheel context menu ───
                         GTA-style radial picker · stage 1 shows a
                         donut of categories, stage 2 expands the
                         selected category into a panel of nodes.
                         The geometry helpers + state live in
                         pageStudioNodeCanvas (wheelSlicePath etc).
                         Centred on the cursor via translate(-50%) ·
                         ctxMenu.x/y are viewport-local. --}}
                    @php
                        $wheelGroups = [];
                        if (! empty($this->variables)) $wheelGroups['variables'] = ['🔣', 'Variables'];
                        $groupIcons = [
                            'source'    => ['⇥', 'Source'],
                            'transform' => ['⚙', 'Transform'],
                            'image'     => ['🖼', 'Image'],
                            'convert'   => ['⇄', 'Convert'],
                            'output'    => ['▶', 'Output'],
                            'note'      => ['📌', 'Note'],
                        ];
                        foreach (array_keys($this->nodeLibrary) as $g) {
                            $wheelGroups[$g] = $groupIcons[$g] ?? ['•', ucfirst($g)];
                        }
                        $wheelGroupList = array_keys($wheelGroups);
                    @endphp
                    <div
                        x-show="ctxMenu.open"
                        x-cloak
                        :style="`top:${ctxMenu.y}px;left:${ctxMenu.x}px`"
                        @click.outside="closeCtxMenu()"
                        @keydown.escape.window="closeCtxMenu()"
                        class="ps-ne-wheel"
                        role="menu"
                    >
                        {{-- Stage 1 · the wheel itself --}}
                        <svg viewBox="0 0 280 280" class="ps-ne-wheel-svg"
                             x-show="! ctxMenu.expandedGroup">
                            <circle cx="140" cy="140" r="50" class="ps-ne-wheel-hub"/>
                            <text x="140" y="138" class="ps-ne-wheel-hub-label"
                                  x-text="ctxMenu.hoveredGroup
                                      ? @js($wheelGroups)[ctxMenu.hoveredGroup]?.[1]
                                      : 'Add node'"></text>
                            <text x="140" y="156" class="ps-ne-wheel-hub-hint">right-click anywhere</text>
                            @foreach ($wheelGroupList as $i => $group)
                                @php $n = count($wheelGroupList); @endphp
                                <g class="ps-ne-wheel-slice"
                                   :class="ctxMenu.hoveredGroup === @js($group) ? 'is-hover' : ''"
                                   @mouseenter="ctxMenu.hoveredGroup = @js($group)"
                                   @mouseleave="ctxMenu.hoveredGroup = null"
                                   @click.stop="ctxMenu.expandedGroup = @js($group)"
                                   role="menuitem">
                                    <path :d="wheelSlicePath({{ $i }}, {{ $n }})" class="ps-ne-wheel-slice-fill ps-ne-wheel-slice--{{ $group }}"/>
                                    @php
                                        $labelExpr = "wheelSliceLabel($i, $n)";
                                    @endphp
                                    <text :x="{{ $labelExpr }}.x"
                                          :y="{{ $labelExpr }}.y - 6"
                                          class="ps-ne-wheel-slice-icon">{{ $wheelGroups[$group][0] }}</text>
                                    <text :x="{{ $labelExpr }}.x"
                                          :y="{{ $labelExpr }}.y + 14"
                                          class="ps-ne-wheel-slice-label">{{ $wheelGroups[$group][1] }}</text>
                                </g>
                            @endforeach
                        </svg>

                        {{-- Stage 2 · the chosen category's items --}}
                        @foreach ($wheelGroupList as $group)
                            <div class="ps-ne-wheel-panel"
                                 x-show="ctxMenu.expandedGroup === @js($group)"
                                 x-cloak>
                                <header class="ps-ne-wheel-panel-bar">
                                    <button type="button" class="ps-ne-wheel-back"
                                            @click.stop="ctxMenu.expandedGroup = null"
                                            title="Back to wheel">←</button>
                                    <span class="ps-ne-wheel-panel-title">{{ $wheelGroups[$group][1] }}</span>
                                </header>
                                <div class="ps-ne-wheel-panel-list">
                                    @if ($group === 'variables')
                                        @foreach ($this->variables as $v)
                                            <button type="button" class="ps-ne-wheel-item"
                                                    @click.stop="dropVarHere(@js($v['name']))"
                                                    role="menuitem">
                                                <span class="ps-ne-wheel-item-icon">🔣</span>
                                                <span class="ps-ne-wheel-item-label">
                                                    <code>&#123;&#123; {{ $v['name'] }} &#125;&#125;</code>
                                                </span>
                                                @if (! empty($v['preview']))
                                                    <span class="ps-ne-wheel-item-hint">{{ \Illuminate\Support\Str::limit((string) $v['preview'], 24) }}</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    @else
                                        @foreach (($this->nodeLibrary)[$group] ?? [] as $key => $def)
                                            <button type="button" class="ps-ne-wheel-item"
                                                    @click.stop="dropNodeHere(@js($key))"
                                                    role="menuitem">
                                                <span class="ps-ne-wheel-item-icon">{{ $def['icon'] ?? '•' }}</span>
                                                <span class="ps-ne-wheel-item-label">{{ $def['label'] ?? $key }}</span>
                                            </button>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
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

                {{-- Right-rail Node Settings used to live here · removed in the
                     on-node settings refactor (Blender-style). Each settable
                     field now renders inside its node card with a wireable
                     socket pip on the left, see the @foreach ($nodes) loop
                     above. --}}
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
            @include('page-studio::livewire._page-builder-scripts')
        @endpush
        @push('scripts')
            @include('page-studio::livewire._page-builder-styles')
        @endpush
    @endonce
</div>
