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
                        // Device-frame preview · 'desktop' | 'tablet' | 'phone'.
                        // Wraps the canvas in a max-width container so authors
                        // can sanity-check the responsive shape without
                        // resizing the browser window itself.
                        device: localStorage.getItem('psPbDevice') || 'desktop',
                        // Node-editor palette is collapsible too · mirrors the
                        // page-builder rails. Defaults closed on phone so the
                        // canvas owns the screen; defaults open on desktop +
                        // tablet for the wider authoring surface.
                        nodePaletteCollapsed: (typeof window !== 'undefined' && window.innerWidth <= 768)
                            ? true
                            : localStorage.getItem('psPbNodePaletteCollapsed') === '1',
                        // Rail widths · authors drag the inner edge of a
                        // rail to resize it. Pixel values, persisted to
                        // localStorage, clamped 120-540 by the drag handler.
                        // Defaults align with the historical CSS vars.
                        leftRailW:    parseInt(localStorage.getItem('psPbLeftRailW')    || '144'),
                        rightRailW:   parseInt(localStorage.getItem('psPbRightRailW')   || '224'),
                        neLeftRailW:  parseInt(localStorage.getItem('psPbNeLeftRailW')  || '160'),
                        neRightRailW: parseInt(localStorage.getItem('psPbNeRightRailW') || '224'),
                        startRailResize(e, which) {
                            const startX = e.clientX;
                            const origin = this[which + 'RailW'];
                            // Left-side rails grow as the cursor moves right;
                            // right-side rails grow as the cursor moves left.
                            const sign = (which === 'left' || which === 'neLeft') ? 1 : -1;
                            const lsKey = {
                                left:    'psPbLeftRailW',
                                right:   'psPbRightRailW',
                                neLeft:  'psPbNeLeftRailW',
                                neRight: 'psPbNeRightRailW',
                            }[which];
                            const move = ev => {
                                const next = Math.max(120, Math.min(540, origin + sign * (ev.clientX - startX)));
                                this[which + 'RailW'] = next;
                                localStorage.setItem(lsKey, String(next));
                            };
                            const up = () => {
                                window.removeEventListener('pointermove', move);
                                window.removeEventListener('pointerup', up);
                                document.body.style.userSelect = '';
                            };
                            window.addEventListener('pointermove', move);
                            window.addEventListener('pointerup', up);
                            document.body.style.userSelect = 'none';
                            e.preventDefault();
                        },
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
                            // current author holds, bump the presence row
                            // for this tab, AND pull any peer edits that
                            // landed in the DB since we last looked. All
                            // three calls no-op server-side in ephemeral
                            // mode, so we don't need to gate the interval.
                            this.lastSyncIso = null;
                            this.collabHeartbeat = setInterval(async () => {
                                const held = this.heldBlockIds();
                                if (held.length > 0) {
                                    this.$wire.heartbeatBlockLocks(held);
                                }
                                this.$wire.heartbeatPresence();
                                // Pull peer edits and merge silently if
                                // no settings input is focused · don't
                                // clobber whatever the user is typing
                                // right now.
                                try {
                                    const u = await this.$wire.pullCollabUpdates(this.lastSyncIso);
                                    if (u && u.updatedAt) {
                                        this.applyCollabUpdate(u);
                                        this.lastSyncIso = u.updatedAt;
                                    }
                                } catch (_) {}
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
                        // Silently merge peer edits returned by
                        // pullCollabUpdates · skipped while an editable
                        // field is focused so we don't stomp on
                        // whatever the user is typing right now. Block
                        // ids the current tab holds a lock on are
                        // preserved from the local tree (the local
                        // wire:model.live value is what the user just
                        // typed; pulling from the DB would erase
                        // sub-debounce keystrokes).
                        applyCollabUpdate(u) {
                            const active = document.activeElement;
                            const isTyping = active && (
                                active.tagName === 'INPUT' ||
                                active.tagName === 'TEXTAREA' ||
                                active.isContentEditable
                            );
                            if (isTyping) return;

                            const heldIds = new Set(this.$wire.heldBlockLockIds || []);
                            if (heldIds.size === 0) {
                                this.$wire.set('blocks', u.blocks);
                            } else {
                                // Walk both trees and replace each block
                                // unless the local tab holds its lock.
                                const localBlocks = JSON.parse(JSON.stringify(this.$wire.blocks || []));
                                const merged = this.mergeBlocksPreservingHeld(localBlocks, u.blocks, heldIds);
                                this.$wire.set('blocks', merged);
                            }
                            this.$wire.set('meta', u.meta);
                        },

                        // Walk two parallel trees and return a copy of
                        // `incoming` with any block whose id is in
                        // `heldIds` swapped for the local version.
                        // Preserves child slots recursively · a peer
                        // edit on the parent doesn't reset the unsaved
                        // edits the local tab has on a nested child.
                        mergeBlocksPreservingHeld(local, incoming, heldIds) {
                            const localById = new Map();
                            const indexLocal = (list) => {
                                for (const b of (list || [])) {
                                    if (b && b.id) localById.set(b.id, b);
                                    if (b && b.children) {
                                        for (const k of Object.keys(b.children)) {
                                            indexLocal(b.children[k]);
                                        }
                                    }
                                }
                            };
                            indexLocal(local);

                            const walk = (list) => {
                                return (list || []).map((b) => {
                                    if (b && b.id && heldIds.has(b.id) && localById.has(b.id)) {
                                        return localById.get(b.id);
                                    }
                                    if (b && b.children) {
                                        const out = { ...b, children: { ...b.children } };
                                        for (const k of Object.keys(out.children)) {
                                            out.children[k] = walk(out.children[k]);
                                        }
                                        return out;
                                    }
                                    return b;
                                });
                            };
                            return walk(incoming);
                        },

                        heldBlockIds() {
                            const sel = this.$wire.selectedPath;
                            if (! sel) return [];
                            const id = this.blockIdForPath(sel);
                            return id ? [id] : [];
                        },

                        get selectedPath() { return this.$wire.selectedPath; },
                        get segmentCount() { return (this.$wire.blocks || []).length; },

                        // Mobile-only · auto-close the left rail (sheet) after
                        // the user picks a palette item, either by tap or by
                        // dragstart. Without this the rail stays in front of
                        // the canvas covering most of the viewport, so a
                        // dragged block has nowhere visible to drop.
                        closeRailOnMobile() {
                            if (typeof window !== 'undefined' && window.innerWidth <= 768) {
                                this.leftCollapsed = true;
                            }
                        },

                        // Same shape as closeRailOnMobile but for the node
                        // editor palette. Lets the node canvas own the
                        // viewport after a palette tap on phone.
                        closeNodePaletteOnMobile() {
                            if (typeof window !== 'undefined' && window.innerWidth <= 768) {
                                this.nodePaletteCollapsed = true;
                            }
                        },

                        // Var-chip drag · while a chip is dragged over a
                        // block in the canvas we paint a small "|" caret
                        // showing the insertion point. On drop the server
                        // method splices the @{{ varname }} token at that
                        // offset.
                        // varDragCaret stores the floating indicator
                        // element so onVarDragLeave can remove it.
                        varDragCaret: null,
                        varDragInsert: { path: '', offset: -1, varName: '' },
                        // Map a caret (text node + offset-in-node) back to an
                        // offset within the block's SOURCE field. Necessary
                        // for blocks whose rendered DOM doesn't match the
                        // source character-for-character — currently the
                        // list block (source = "line\nline", DOM = N <li>s).
                        // Other text-rich blocks (heading / paragraph /
                        // quote / code / button label / hero) render their
                        // source as a single text node, so the raw caret
                        // offset is already the source offset.
                        mapCaretToSourceOffset(block, node, rawOffset) {
                            if (! node || rawOffset == null || rawOffset < 0) return rawOffset;
                            const type = block.dataset?.blockType || '';
                            if (type === 'list') {
                                // Find the <li> ancestor of the caret
                                // position · its index among sibling <li>s
                                // tells us which source line we're on.
                                let li = node.nodeType === 3 ? node.parentElement : node;
                                while (li && li.tagName !== 'LI') li = li.parentElement;
                                if (! li) return rawOffset;
                                const ul   = li.parentElement;
                                const lis  = ul ? Array.from(ul.children).filter(c => c.tagName === 'LI') : [li];
                                const idx  = lis.indexOf(li);
                                let preLen = 0;
                                for (let i = 0; i < idx; i++) {
                                    preLen += (lis[i].textContent || '').length + 1; // +1 for the source \n
                                }
                                // Measure the in-line offset by counting
                                // characters between the <li>'s start and
                                // the caret position. Using a Range +
                                // toString().length works whether the
                                // browser handed us a (text-node, char)
                                // pair OR an (element, child-index) pair
                                // — the latter happens when the caret
                                // lands past the visible text but still
                                // inside the <li>'s wider box, and was
                                // the root cause of "drop at end goes
                                // mid-word". Cap to the li's own length
                                // so a caret further down the page
                                // doesn't overshoot this line.
                                let inLine = rawOffset;
                                try {
                                    const r = document.createRange();
                                    r.setStart(li, 0);
                                    r.setEnd(node, Math.min(rawOffset, (node.textContent || '').length));
                                    inLine = r.toString().length;
                                } catch (_) {}
                                const liLen = (li.textContent || '').length;
                                if (inLine > liLen) inLine = liLen;
                                return preLen + inLine;
                            }
                            // Default: source is the single text node we
                            // landed on — raw offset matches.
                            return rawOffset;
                        },
                        onVarDragOver(event, path) {
                            // Only act when the drag payload is a var chip
                            // (the strip set application/x-page-studio-var).
                            const types = event.dataTransfer?.types || [];
                            const has = Array.from(types).includes('application/x-page-studio-var');
                            if (! has) return;
                            event.preventDefault();
                            event.stopPropagation();

                            const varName = event.dataTransfer.getData('application/x-page-studio-var') || '';
                            const block = event.currentTarget;
                            // Find the rendered-text element inside the
                            // block so caretPositionFromPoint snaps to a
                            // character. .ps-pb-block-render is the inline
                            // preview; settings-driven blocks render here.
                            const target = block.querySelector('.ps-pb-block-render') || block;
                            let offset = -1, caretX = event.clientX, caretY = event.clientY;
                            const cp = (document.caretPositionFromPoint && document.caretPositionFromPoint(event.clientX, event.clientY))
                                || (document.caretRangeFromPoint && document.caretRangeFromPoint(event.clientX, event.clientY));
                            if (cp) {
                                const rawOffset = cp.offset ?? cp.startOffset ?? -1;
                                const node = cp.offsetNode || cp.startContainer;
                                if (node) {
                                    const range = document.createRange();
                                    try {
                                        range.setStart(node, Math.min(rawOffset, (node.textContent || '').length));
                                        range.setEnd(node, Math.min(rawOffset, (node.textContent || '').length));
                                        const r = range.getBoundingClientRect();
                                        if (r.height) { caretX = r.left; caretY = r.top; }
                                    } catch (_) {}
                                }
                                // Convert the text-node-relative caret offset
                                // into an offset within the BLOCK SOURCE
                                // (block.settings[fieldKey]). For most blocks
                                // this is identity (one text node holds the
                                // whole source). For list blocks, the source
                                // is line-joined with \n but rendered as N
                                // separate <li>s — so we sum prior <li> text
                                // lengths and add a \n per crossed line.
                                offset = this.mapCaretToSourceOffset(block, node, rawOffset);
                            }

                            this.varDragInsert = { path, offset, varName };

                            // Paint / move the caret indicator.
                            if (! this.varDragCaret) {
                                const el = document.createElement('div');
                                el.className = 'ps-pb-var-drop-caret';
                                document.body.appendChild(el);
                                this.varDragCaret = el;
                            }
                            const c = this.varDragCaret;
                            c.style.left = caretX + 'px';
                            c.style.top  = caretY + 'px';
                            const tr = target.getBoundingClientRect();
                            c.style.height = (tr.height > 0 ? Math.min(tr.height, 28) : 18) + 'px';
                        },
                        onVarDragLeave(event) {
                            const types = event.dataTransfer?.types || [];
                            if (! Array.from(types).includes('application/x-page-studio-var')) return;
                            if (this.varDragCaret) {
                                this.varDragCaret.remove();
                                this.varDragCaret = null;
                            }
                        },
                        onVarDrop(event, path) {
                            const types = event.dataTransfer?.types || [];
                            if (! Array.from(types).includes('application/x-page-studio-var')) return;
                            event.preventDefault();
                            event.stopPropagation();

                            const varName = event.dataTransfer.getData('application/x-page-studio-var') || '';
                            if (this.varDragCaret) {
                                this.varDragCaret.remove();
                                this.varDragCaret = null;
                            }
                            if (! varName) return;
                            this.$wire.insertVarIntoBlock(path, varName, null, this.varDragInsert.offset ?? -1);
                        },

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
                            // Touch pan · single-finger drag on the canvas
                            // background pans the stage. The same gesture
                            // on desktop is reserved for marquee selection,
                            // but touchscreens have no middle-mouse / Alt
                            // shortcut, so a finger drag IS the pan
                            // gesture. Nodes / sockets / viewport controls
                            // get a pass · they have their own
                            // pointerdown handlers.
                            if (e.pointerType === 'touch'
                                && ! e.target.closest('.ps-ne-node, .ps-ne-socket, .ps-ne-viewport-ctl, .ps-ne-wire')) {
                                e.preventDefault();
                                this.panDrag = {
                                    startX: e.clientX, startY: e.clientY,
                                    originX: this.pan.x, originY: this.pan.y,
                                };
                                const onMove = (ev) => {
                                    if (ev.pointerType !== 'touch') return;
                                    this.pan = {
                                        x: this.panDrag.originX + (ev.clientX - this.panDrag.startX),
                                        y: this.panDrag.originY + (ev.clientY - this.panDrag.startY),
                                    };
                                    this.queueRedraw();
                                };
                                const onUp = () => {
                                    window.removeEventListener('pointermove', onMove);
                                    window.removeEventListener('pointerup',   onUp);
                                    window.removeEventListener('pointercancel', onUp);
                                    this.panDrag = null;
                                };
                                window.addEventListener('pointermove', onMove);
                                window.addEventListener('pointerup',   onUp);
                                window.addEventListener('pointercancel', onUp);
                                return;
                            }

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
