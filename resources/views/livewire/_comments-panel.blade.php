{{-- Review-thread panel · shares the right rail with the block-settings
     view. Lists every open thread on the page; when a block is selected
     the matching thread is highlighted and the compose form posts to it. --}}
@php
    $authId   = auth()->id();
    $threads  = $this->blockComments;
    $selected = $this->selectedBlock;
    $selBlockId = $selected['id'] ?? null;
    $pageBound  = ($pageId !== null || $routeId !== null);
@endphp

<section class="ps-pb-section ps-pb-comments-section">
    <h3>Comments</h3>

    @if (! $pageBound)
        <p class="ps-pb-hint">Comments need a saved page · save the page once to start a review thread.</p>
    @elseif (empty($threads) && ! $selected)
        <p class="ps-pb-hint">No open comments yet. Select a block on the canvas and drop a note for the reviewer.</p>
    @else
        @if ($selected)
            <p class="ps-pb-hint">Thread for <code>{{ $selected['type'] }}</code></p>
        @endif

        @foreach ($threads as $blockId => $threadList)
            <div class="ps-pb-comment-thread {{ $selBlockId === $blockId ? 'is-selected-block' : '' }}">
                @if ($selBlockId !== $blockId)
                    @php
                        // Compact label · the rail can't render the full
                        // block, so show the type + a short snippet from
                        // the block's first text-ish setting. Look up the
                        // block by id once on render.
                        $blockType = 'block';
                    @endphp
                    <button type="button"
                            class="ps-pb-comment-block-jump"
                            wire:click="$set('rightRailView', 'comments')"
                            title="Comments on another block">
                        On another block ({{ count($threadList) }})
                    </button>
                @endif

                @foreach ($threadList as $thread)
                    <div class="ps-pb-comment-row" data-comment-id="{{ $thread['id'] }}">
                        <div class="ps-pb-comment-head">
                            <span class="ps-pb-comment-author">{{ $thread['author_name'] ?? 'Reviewer' }}</span>
                            <span class="ps-pb-comment-time" title="{{ $thread['created_at'] }}">{{ $thread['created_at'] }}</span>
                        </div>
                        <div class="ps-pb-comment-body">{{ $thread['body'] }}</div>
                        <div class="ps-pb-comment-actions">
                            <button type="button"
                                    wire:click="startReply({{ $thread['id'] }})"
                                    class="ps-pb-comment-action">Reply</button>
                            @if ($thread['resolved'])
                                <button type="button"
                                        wire:click="reopenComment({{ $thread['id'] }})"
                                        class="ps-pb-comment-action">Re-open</button>
                            @else
                                <button type="button"
                                        wire:click="resolveComment({{ $thread['id'] }})"
                                        class="ps-pb-comment-action">Resolve</button>
                            @endif
                            @if ($authId !== null && (int) ($thread['author_id'] ?? 0) === (int) $authId)
                                <button type="button"
                                        wire:click="deleteComment({{ $thread['id'] }})"
                                        class="ps-pb-comment-action ps-pb-comment-danger">Delete</button>
                            @endif
                        </div>

                        @foreach ($thread['replies'] ?? [] as $reply)
                            <div class="ps-pb-comment-row ps-pb-comment-reply" data-comment-id="{{ $reply['id'] }}">
                                <div class="ps-pb-comment-head">
                                    <span class="ps-pb-comment-author">{{ $reply['author_name'] ?? 'Reviewer' }}</span>
                                    <span class="ps-pb-comment-time" title="{{ $reply['created_at'] }}">{{ $reply['created_at'] }}</span>
                                </div>
                                <div class="ps-pb-comment-body">{{ $reply['body'] }}</div>
                                <div class="ps-pb-comment-actions">
                                    @if ($authId !== null && (int) ($reply['author_id'] ?? 0) === (int) $authId)
                                        <button type="button"
                                                wire:click="deleteComment({{ $reply['id'] }})"
                                                class="ps-pb-comment-action ps-pb-comment-danger">Delete</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach

        {{-- Compose form · posts against the selected block, or as a
             reply when startReply primed $replyingTo. --}}
        @if ($selected)
            <div class="ps-pb-comment-compose">
                @if ($replyingTo !== null)
                    <div class="ps-pb-comment-replying">
                        Replying to comment #{{ $replyingTo }}
                        <button type="button"
                                wire:click="cancelReply"
                                class="ps-pb-comment-action">Cancel</button>
                    </div>
                @endif
                <textarea
                    wire:model.live.debounce.150ms="newCommentBody"
                    rows="3"
                    placeholder="Leave a note for the reviewer..."></textarea>
                <button type="button"
                        wire:click="postCurrentComment"
                        class="ps-pb-btn ps-pb-btn--primary">
                    Post
                </button>
            </div>
        @else
            <p class="ps-pb-hint">Click a block to add a comment.</p>
        @endif
    @endif
</section>
