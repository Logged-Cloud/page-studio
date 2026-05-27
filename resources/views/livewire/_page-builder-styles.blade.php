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
                    /* Per-rail widths come from Alpine via the :style binding ·
                       --rail-l / --rail-r fall back to the historical fixed
                       width if Alpine hasn't booted yet. */
                    grid-template-columns: var(--rail-l, var(--rail-w)) 1fr var(--rail-r, var(--rail-w));
                    grid-template-areas: "left canvas right";
                    gap: 0;
                    flex: 1;
                    min-height: 0;
                    overflow: hidden;
                    /* Reserve viewport room for the fixed Variables Modifier
                       drawer + the variables strip floating just above it.
                       Without this the bottom of the left rail (block
                       palette) and the canvas slide UNDER the strip and
                       drawer rather than alongside them. */
                    padding-bottom: calc(var(--ps-pb-drawer-h, 0px) + 3.25rem);
                    box-sizing: border-box;
                }
                .ps-pb-grid.is-left-collapsed  { grid-template-columns: 0 1fr var(--rail-r, var(--rail-w)); }
                .ps-pb-grid.is-right-collapsed { grid-template-columns: var(--rail-l, var(--rail-w)) 1fr 0; }
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
                    /* Selector bumped to .ps-pb-grid .ps-pb-rail so this
                       beats the later unqualified .ps-pb-rail base rule
                       in the cascade · before the bump the sheet showed a
                       near-transparent background and the canvas blocks
                       bled through. */
                    .ps-pb-grid .ps-pb-rail {
                        position: fixed;
                        top: 0;
                        bottom: 0;
                        width: min(85vw, 22rem);
                        z-index: 400;
                        background: var(--surface-2, #1E1F22);
                        transition: transform .2s ease;
                        box-shadow: 0 8px 32px rgba(0,0,0,.45);
                    }
                    /* Palette renders as a 2-column grid on phone so the
                       full block library fits in fewer rows · the rail is
                       already a sheet so vertical scrolling is fine, but
                       tighter density makes the list approachable. The
                       .ps-pb-grid prefix bumps specificity past the base
                       .ps-pb-palette rule that follows in source order. */
                    .ps-pb-grid .ps-pb-palette { grid-template-columns: 1fr 1fr; gap: .35rem; }
                    .ps-pb-grid .ps-pb-palette-item { padding: .5rem .55rem; font-size: .8rem; }
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
                    /* Topbar wraps on narrow screens · save / preview keep
                       their CTA-shape but the URL chip + toggles flow.
                       The actions group also wraps so the publish + save
                       buttons sit on a second line rather than overflowing
                       horizontally and clipping at the viewport edge. */
                    .ps-pb-topbar { flex-wrap: wrap; gap: .35rem; padding: .5rem .65rem; }
                    .ps-pb-topbar code.ps-pb-path { font-size: .75rem; max-width: 60vw; overflow: hidden; text-overflow: ellipsis; }
                    .ps-pb-actions { flex-wrap: wrap; gap: .35rem; row-gap: .35rem; }
                    .ps-pb-btn { padding: .3rem .55rem; font-size: .75rem; }
                    .ps-pb-save-btn { min-width: 0; flex: 1 1 7rem; }
                    /* Hide the device-frame toggle on phone · device-frame
                       previews don't add value when the editor is already
                       on a phone-shaped viewport. */
                    .ps-pb-device-toggle { display: none; }
                    /* Email-meta strip also wraps + tightens. */
                    .ps-pb-email-meta { grid-template-columns: 1fr !important; gap: .35rem !important; padding: .5rem .65rem !important; }
                    /* Drawer goes full-screen on mobile · the whole
                       viewport becomes the editor. The drawer's own
                       header carries the close button (the tuck
                       handle is display:none while open). 100dvh
                       respects the mobile address-bar shrink/grow. */
                    .ps-ne-drawer {
                        top: 0 !important;
                        left: 0 !important;
                        right: 0 !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        height: 100dvh !important;
                        z-index: 70 !important;
                    }
                    /* The JS-driven --ps-pb-drawer-h CSS variable
                       carries the desktop drawer height (352px by
                       default) and doesn't always reset to 0 when
                       the drawer closes · so the page-builder grid
                       was reserving 404px of padding-bottom for a
                       drawer that's actually full-screen on mobile.
                       Force it to 0 on phones · the drawer covers
                       the whole viewport when open anyway, so there
                       is nothing to reserve. */
                    :root { --ps-pb-drawer-h: 0px !important; }

                    /* Var-strip pinned to the bottom edge regardless
                       of the JS-driven --ps-pb-drawer-h. z-index
                       above the drawer so it stays visible +
                       draggable while the drawer is open. */
                    .ps-pb-var-strip {
                        bottom: 0 !important;
                        z-index: 80 !important;
                    }
                    /* Tuck handle should float ABOVE the var-strip so
                       the two don't overlap at the bottom of the
                       screen. The strip is ~42px tall · push the
                       handle up by that + a small gap. z-index is
                       higher than the strip so the pill renders on
                       top in case of any sliver of overlap. */
                    .ps-ne-tuck-handle {
                        bottom: 54px !important;
                        z-index: 85 !important;
                    }
                    /* Make absolutely sure the canvas inside the
                       drawer reclaims the full width on mobile · the
                       grid drops its palette / settings columns and
                       the canvas + stage stretch to 100%. */
                    .ps-ne-canvas-wrap,
                    .ps-ne-stage-wrap,
                    .ps-ne-canvas { width: 100% !important; }
                    .ps-ne-grabber { display: none; }
                    /* Phone grid: ONE column · the palette + settings
                       are absolute/fixed sheets on mobile (rules
                       below) so the grid only needs to hand the
                       canvas its full width. Source-order rules
                       elsewhere in this file would otherwise reapply
                       the desktop 3-column layout (palette + canvas
                       + settings), squishing the canvas to a thin
                       middle column · !important wins the cascade. */
                    .ps-ne-grid {
                        grid-template-columns: 1fr !important;
                        grid-template-areas: "canvas" !important;
                    }
                    .ps-ne-grid--palette-closed { grid-template-columns: 1fr !important; }
                    /* Node-editor palette + settings on phone · fixed sheets
                       slid in from the edges. Visibility is now driven by
                       x-show on the elements themselves (nodePaletteCollapsed +
                       selectedNode), so the panels are display:none when
                       closed · no translateX off-screen sleight of hand. */
                    .ps-ne-grid .ps-ne-palette,
                    .ps-ne-grid .ps-ne-settings {
                        position: fixed;
                        top: 20vh; bottom: 0;
                        width: min(85vw, 22rem);
                        z-index: 401;
                        background: var(--surface-2, #1E1F22);
                        box-shadow: 0 8px 32px rgba(0,0,0,.45);
                    }
                    .ps-ne-grid .ps-ne-palette  { left: 0; }
                    .ps-ne-grid .ps-ne-settings { right: 0; left: auto; }
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
                .ps-pb-canvas-wrap  { grid-area: canvas; background: var(--surface, #16171a); padding: 1.25rem 1.25rem 4rem; overflow-y: auto; }
                /* Device-frame previews · the canvas is centred and its
                   max-width is clamped to a typical viewport size for that
                   form factor. Pure visual constraint, no responsive CSS
                   inside the canvas blocks · the host theme owns that. */
                .ps-pb-canvas-wrap--tablet .ps-pb-canvas { max-width: 48rem; margin-left: auto; margin-right: auto; box-shadow: 0 0 0 1px var(--line, #3A3D40); }
                .ps-pb-canvas-wrap--phone  .ps-pb-canvas { max-width: 24.375rem; margin-left: auto; margin-right: auto; box-shadow: 0 0 0 1px var(--line, #3A3D40); }
                .ps-pb-device-toggle { display: inline-flex; gap: 1px; border: 1px solid var(--line, #3A3D40); border-radius: .35rem; overflow: hidden; }
                .ps-pb-device-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: .25rem;
                    background: transparent; color: var(--ink-dim, #A3A099);
                    border: 0; padding: .35rem .65rem;
                    cursor: pointer; font: inherit; font-size: .8rem;
                    line-height: 1;
                    white-space: nowrap;
                }
                .ps-pb-device-btn.is-active { background: rgba(255,255,255,.05); color: var(--ink, #F0EDE5); }
                .ps-pb-device-btn:hover { color: var(--ink, #F0EDE5); }
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
                /* SEO + future page-meta fields use the same shape. */
                .ps-pb-meta-field { display: flex; flex-direction: column; gap: .2rem; font-size: .8rem; margin-bottom: .65rem; }
                .ps-pb-meta-field span { color: var(--ink-dim, #A3A099); text-transform: uppercase; letter-spacing: .06em; font-size: .65rem; }
                .ps-pb-meta-input {
                    background: var(--surface, #16171a);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .25rem;
                    padding: .35rem .55rem;
                    font-size: .85rem;
                    outline: none;
                    font-family: inherit;
                }
                .ps-pb-meta-input:focus { border-color: var(--accent, #2C66E8); }
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

                /* Device-frame overrides for both preview pane AND edit
                   canvas. The columns blocks emit a viewport-width @media
                   query that doesn't fire when only the canvas-wrap is
                   phone-sized. Parent-class selectors with !important so
                   they beat the inline grid-template-columns the block
                   render emits. Same shape covers the edit-mode layout
                   frame (block-editor slots) so authors see the collapsed
                   shape immediately when they pick Phone in the topbar. */
                .ps-pb-preview-pane--phone .ps-render-cols-2,
                .ps-pb-preview-pane--phone .ps-render-cols-3,
                .ps-pb-canvas-wrap--phone  .ps-render-cols-2,
                .ps-pb-canvas-wrap--phone  .ps-render-cols-3,
                .ps-pb-canvas-wrap--phone  .ps-pb-layout--slots-2,
                .ps-pb-canvas-wrap--phone  .ps-pb-layout--slots-3 {
                    grid-template-columns: 1fr !important;
                    gap: 1.5rem !important;
                }
                .ps-pb-preview-pane--tablet .ps-render-cols-3,
                .ps-pb-canvas-wrap--tablet  .ps-render-cols-3,
                .ps-pb-canvas-wrap--tablet  .ps-pb-layout--slots-3 {
                    grid-template-columns: 1fr 1fr !important;
                }

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

                /* ─── Variable drag-into-block caret ─────────────────────
                   A thin vertical bar pinned to document.body that follows
                   the cursor while a var chip is dragged over a block.
                   Marks the insertion point so authors see where the
                   token will land · `caretPositionFromPoint` computes the
                   character offset, this just paints it. */
                .ps-pb-var-drop-caret {
                    position: fixed;
                    width: 2px;
                    height: 18px;
                    background: var(--accent, #2C66E8);
                    pointer-events: none;
                    z-index: 9999;
                    box-shadow: 0 0 6px color-mix(in srgb, var(--accent, #2C66E8) 60%, transparent);
                    animation: ps-pb-var-caret-pulse 1s steps(2) infinite;
                }
                @keyframes ps-pb-var-caret-pulse {
                    50% { opacity: .4; }
                }

                /* ─── Rail resize grabbers ────────────────────────────────
                   A 5px-wide strip on the inner edge of each rail. Cursor
                   tweens to col-resize, drag updates the matching Alpine
                   width state via startRailResize(). Positioned absolute
                   inside the rail so the rail's normal padding doesn't
                   shift it; pointer-events stay live across the full
                   strip. */
                .ps-pb-rail, .ps-ne-palette, .ps-ne-settings { position: relative; }
                .ps-pb-rail-grabber, .ps-ne-rail-grabber {
                    position: absolute;
                    top: 0; bottom: 0;
                    width: 6px;
                    background: transparent;
                    border: 0;
                    cursor: col-resize;
                    z-index: 30;
                    padding: 0;
                }
                .ps-pb-rail-grabber:hover, .ps-pb-rail-grabber:active,
                .ps-ne-rail-grabber:hover, .ps-ne-rail-grabber:active {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 50%, transparent);
                }
                .ps-pb-rail-grabber--right, .ps-ne-rail-grabber--right { right: -3px; }
                .ps-pb-rail-grabber--left,  .ps-ne-rail-grabber--left  { left:  -3px; }

                /* ─── Variables strip ─────────────────────────────────────
                   Persistent horizontal marquee of variable chips sitting
                   just above the Variables Modifier drawer. The strip is
                   position:fixed across the bottom of the viewport, riding
                   up when the drawer opens (its bottom tracks the drawer's
                   top via --ps-pb-drawer-h). overflow-x:auto + flex-shrink:0
                   on the chips means a long variable list scrolls
                   horizontally without wrapping. */
                .ps-pb-var-strip {
                    position: fixed;
                    left: 0; right: 0;
                    bottom: calc(var(--ps-pb-drawer-h, 0) + 8px);
                    z-index: 55;
                    display: flex;
                    align-items: center;
                    gap: .55rem;
                    padding: .35rem .65rem;
                    background: color-mix(in srgb, var(--surface-2, #1E1F22) 92%, transparent);
                    border-top: 1px solid var(--line, #3A3D40);
                    backdrop-filter: blur(8px);
                    overflow-x: auto;
                    overflow-y: hidden;
                    scrollbar-width: thin;
                }
                .ps-pb-var-strip-label {
                    flex-shrink: 0;
                    font-size: .65rem;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    color: var(--ink-dim, #A3A099);
                    padding-right: .35rem;
                    border-right: 1px solid var(--line, #3A3D40);
                }
                .ps-pb-var-strip-track {
                    display: flex;
                    align-items: center;
                    gap: .35rem;
                    flex: 1;
                    min-width: 0;
                }
                .ps-pb-var-chip {
                    flex-shrink: 0;
                    background: var(--surface, #16171a);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: 999px;
                    padding: .25rem .65rem;
                    font: inherit;
                    font-size: .75rem;
                    cursor: grab;
                    display: inline-flex;
                    align-items: center;
                    gap: .25rem;
                    white-space: nowrap;
                    transition: border-color .15s, background-color .15s;
                }
                .ps-pb-var-chip:hover {
                    border-color: color-mix(in srgb, var(--accent, #2C66E8) 60%, var(--line, #3A3D40));
                    background: color-mix(in srgb, var(--accent, #2C66E8) 12%, var(--surface, #16171a));
                }
                .ps-pb-var-chip:active { cursor: grabbing; }
                .ps-pb-var-chip-tok { color: var(--ink-dim, #A3A099); font-family: ui-monospace, monospace; }
                .ps-pb-var-chip-name { font-weight: 600; }
                .ps-pb-var-chip-preview { color: var(--ink-dim, #A3A099); font-style: italic; }

                /* ─── Mobile · the strip was cramped on narrow viewports
                   because every chip carried braces + name + preview +
                   the "Variables" label ate another 3rem on the left.
                   Below 768px we drop the label + preview and tighten
                   the chip padding so a few chips actually fit before
                   the row has to scroll. */
                @media (max-width: 768px) {
                    .ps-pb-var-strip {
                        padding: .25rem .35rem;
                        gap: .25rem;
                    }
                    .ps-pb-var-strip-label { display: none; }
                    .ps-pb-var-chip {
                        padding: .15rem .45rem;
                        font-size: .7rem;
                        gap: .15rem;
                    }
                    .ps-pb-var-chip-preview { display: none; }
                }

                /* ─── Node-editor drawer ──────────────────────────────────
                   Fixed-position so a long canvas never pushes the drawer
                   below the fold. The tuck handle (below) is the persistent
                   affordance to open / close it · same pattern as the
                   logged-cloud/navigation chrome's tuck. */
                .ps-ne-drawer {
                    position: fixed;
                    left: 0; right: 0; bottom: 0;
                    z-index: 60;
                    flex-shrink: 0;
                    background: var(--surface-2, #1E1F22);
                    border-top: 1px solid var(--line, #3A3D40);
                    display: flex;
                    flex-direction: column;
                    box-shadow: 0 -8px 32px rgba(0,0,0,.35);
                }

                /* ─── Tuck handle ─────────────────────────────────────────
                   Always-visible pill at the bottom-centre of the viewport,
                   z-indexed above the drawer so the user can re-tap to
                   close. When the drawer is closed the handle sits a couple
                   of px above the floor; when open it slides up along with
                   the drawer's top edge so the tap target stays near where
                   the eye is. */
                .ps-ne-tuck-handle {
                    position: fixed;
                    left: 50%;
                    transform: translateX(-50%);
                    bottom: 12px;
                    z-index: 65;
                    background: var(--surface-2, #1E1F22);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: 999px;
                    padding: .35rem .9rem .35rem .65rem;
                    font: inherit;
                    font-size: .8rem;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: .45rem;
                    box-shadow: 0 6px 20px rgba(0,0,0,.45);
                    backdrop-filter: blur(8px);
                    transition: background-color .15s, border-color .15s, transform .15s;
                }
                .ps-ne-tuck-handle:hover {
                    background: color-mix(in srgb, var(--accent, #2C66E8) 18%, var(--surface-2, #1E1F22));
                    border-color: color-mix(in srgb, var(--accent, #2C66E8) 40%, var(--line, #3A3D40));
                }
                /* When the drawer is open the handle hides · the drawer's
                   own header carries the close button, and overlapping the
                   handle with the drawer header looks busy. */
                .ps-ne-tuck-handle.is-open { display: none; }
                .ps-ne-tuck-grip {
                    width: 28px; height: 4px; border-radius: 2px;
                    background: var(--ink-dim, #A3A099);
                    display: inline-block;
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
                    /* Per-rail widths from Alpine. Fall back to the historical
                       fixed widths if Alpine hasn't booted yet. */
                    grid-template-columns: var(--ne-rail-l, 10rem) 1fr var(--ne-rail-r, 14rem);
                    flex: 1;
                    min-height: 0;
                }
                /* When the palette is collapsed via the toggle, drop its
                   column so the canvas reclaims the freed width. */
                .ps-ne-grid--palette-closed {
                    grid-template-columns: 1fr var(--ne-rail-r, 14rem);
                }
                .ps-ne-grid--palette-closed:not(:has(.ps-ne-settings)) {
                    grid-template-columns: 1fr;
                }

                /* When the settings aside is removed (no node selected) the
                   centre canvas reclaims the freed column. */
                .ps-ne-grid:not(:has(.ps-ne-settings)) {
                    grid-template-columns: var(--ne-rail-l, 10rem) 1fr;
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

                /* ─── On-node settings rows (Blender-style) ──────
                   Each settable field renders as a row with a socket
                   pip on the left, a label, and the input control on
                   the right. When wired (.is-wired) the control fades
                   out and the pip lights up. */
                .ps-ne-setting-row {
                    display: grid;
                    grid-template-columns: .65rem 1fr minmax(5rem, auto);
                    align-items: center;
                    gap: .5rem;
                    padding: .15rem .35rem .15rem 0;
                    font-size: .72rem;
                    border-top: 1px solid color-mix(in srgb, var(--line, #3A3D40) 60%, transparent);
                }
                .ps-ne-setting-row:first-of-type { border-top: none; }
                .ps-ne-setting-row.is-wired .ps-ne-socket { background: var(--accent, #2C66E8); }
                .ps-ne-setting-label {
                    color: var(--ink-dim, #A3A099);
                    text-transform: none;
                    letter-spacing: 0;
                    font-size: .7rem;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .ps-ne-setting-control { display: inline-flex; align-items: center; gap: .25rem; }
                .ps-ne-setting-control input[type="text"],
                .ps-ne-setting-control input[type="number"],
                .ps-ne-setting-control select,
                .ps-ne-setting-control textarea {
                    background: var(--surface, #16171a);
                    color: var(--ink, #F0EDE5);
                    border: 1px solid var(--line, #3A3D40);
                    border-radius: .25rem;
                    padding: .15rem .35rem;
                    font: inherit;
                    font-size: .7rem;
                    min-width: 4.5rem;
                    width: 100%;
                    max-width: 9rem;
                }
                .ps-ne-setting-control input[type="color"] {
                    width: 1.5rem; height: 1.1rem; padding: 0; border: 1px solid var(--line, #3A3D40); border-radius: .25rem;
                    background: transparent;
                }
                .ps-ne-socket--placeholder {
                    visibility: hidden;
                    pointer-events: none;
                    cursor: default;
                }
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
                    display: inline-flex;
                    align-items: center;
                    gap: .5rem;
                }
                .ps-pb-lock-takeover {
                    background: rgba(255,255,255,.18);
                    color: #fff;
                    border: 1px solid rgba(255,255,255,.45);
                    border-radius: .2rem;
                    padding: .05rem .35rem;
                    font: inherit;
                    font-size: .65rem;
                    letter-spacing: .02em;
                    cursor: pointer;
                }
                .ps-pb-lock-takeover:hover { background: rgba(255,255,255,.32); }
                /* Self-lock variant · green pill, no danger framing. */
                .ps-pb-lock-ribbon--self {
                    background: var(--success, #16a34a);
                    color: #fff;
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
