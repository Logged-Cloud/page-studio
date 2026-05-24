{{-- Single block rendered inside the editor canvas. Recurses into slots when
     the block has any. Expected variables:
       $block       - the block array
       $path        - canonical path to this block (e.g. "0/body/2")
       $parentPath  - path to the list this block lives in (e.g. "0/body")
       $slot        - slot key (null for root)
       $index       - position within the parent slot
       $varCtx      - resolved variable context for inline preview
--}}
@php $schema = config('page-studio.blocks.'.$block['type'], []); @endphp
@php $slotJson = $slot === null ? 'null' : "'".$slot."'"; @endphp
@php
    // Collaboration · is another author currently editing this block?
    // activeBlockLocks is keyed by block id and only contains OTHER users'
    // claims, so a hit here means we should render the read-only ribbon.
    $locks    = $this->activeBlockLocks;
    $lockedBy = $locks[$block['id']]['name'] ?? null;
@endphp

<div
    class="ps-pb-block-wrap {{ $lockedBy ? 'is-locked' : '' }}"
    :class="selectedPath === @js($path) ? 'is-selected' : ''"
    wire:key="block-{{ $block['id'] }}"
    data-block-path="{{ $path }}"
    data-parent-path="{{ $parentPath }}"
    data-slot="{{ $slot === null ? '' : $slot }}"
    data-index="{{ $index }}"
    @click.stop="$wire.selectBlock(@js($path))"
    @contextmenu.prevent.stop="openBlockCtxMenu($event, @js($path))"
    draggable="true"
    @dragstart.stop="onBlockDragStart($event, @js($path))"
    @dragover.prevent.stop="onBlockDragOver($event, @js($parentPath), {{ $slotJson }}, {{ $index }})"
    @drop.prevent.stop="onBlockDrop($event, @js($parentPath), {{ $slotJson }}, {{ $index }})"
    @pointerdown="startTouchDrag($event, 'block', @js($path), @js($block['type']))"
>
    @if ($lockedBy)
        {{-- Lock ribbon · purely informational, never captures pointer
             events so the block underneath stays readable / scrollable. --}}
        <div class="ps-pb-lock-ribbon" style="pointer-events:none">
            🔒 {{ $lockedBy }} editing
        </div>
    @endif

    <div class="ps-pb-block-handle">
        <span class="ps-pb-block-type">{{ $schema['label'] ?? $block['type'] }}</span>
        <div class="ps-pb-block-controls">
            <button type="button"
                    wire:click.stop="moveSibling(@js($parentPath), {{ $slotJson }}, {{ $index }}, -1)"
                    title="Move up">↑</button>
            <button type="button"
                    wire:click.stop="moveSibling(@js($parentPath), {{ $slotJson }}, {{ $index }}, 1)"
                    title="Move down">↓</button>
            <button type="button"
                    wire:click.stop="removeBlock(@js($path))"
                    class="ps-pb-block-danger" title="Delete">✕</button>
        </div>
    </div>

    @if (! empty($schema['slots']))
        {{-- Layout container · slots are drop targets that hold nested blocks. --}}
        <div class="ps-pb-layout-frame
                   ps-pb-layout--{{ $block['type'] }}
                   ps-pb-layout--slots-{{ count($schema['slots']) }}">
            @foreach ($schema['slots'] as $slotKey => $slotLabel)
                @php $kids = $block['children'][$slotKey] ?? []; @endphp
                <div class="ps-pb-slot"
                     data-slot="{{ $slotKey }}"
                     data-parent-path="{{ $path }}"
                     data-kid-count="{{ count($kids) }}"
                     @dragover.prevent.stop="onSlotDragOver($event, @js($path), @js($slotKey), {{ count($kids) }})"
                     @drop.prevent.stop="onSlotDrop($event, @js($path), @js($slotKey))">
                    <div class="ps-pb-slot-label">{{ is_array($slotLabel) ? ($slotLabel['label'] ?? $slotKey) : $slotLabel }}</div>
                    <div class="ps-pb-slot-body">
                        @if (empty($kids))
                            <div class="ps-pb-slot-empty"
                                 :class="dropTarget.parentPath === @js($path) && dropTarget.slot === @js($slotKey) ? 'is-active' : ''">
                                Drop a block here
                            </div>
                        @endif

                        {{-- drop indicator above the first child · only when the slot has kids --}}
                        @if (! empty($kids))
                            <div class="ps-pb-drop-line"
                                 x-show="dropTarget.parentPath === @js($path) && dropTarget.slot === @js($slotKey) && dropTarget.index === 0"
                                 x-cloak></div>
                        @endif

                        @foreach ($kids as $j => $kid)
                            @include('page-studio::livewire._block-editor', [
                                'block'      => $kid,
                                'path'       => $path.'/'.$slotKey.'/'.$j,
                                'parentPath' => $path,
                                'slot'       => $slotKey,
                                'index'      => $j,
                                'varCtx'     => $varCtx,
                            ])
                            <div class="ps-pb-drop-line"
                                 x-show="dropTarget.parentPath === @js($path) && dropTarget.slot === @js($slotKey) && dropTarget.index === {{ $j + 1 }}"
                                 x-cloak></div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="ps-pb-block-render">
            {!! \LoggedCloud\PageStudio\Support\PageRenderer::renderBlock($block, $varCtx, true) !!}
        </div>
    @endif
</div>
