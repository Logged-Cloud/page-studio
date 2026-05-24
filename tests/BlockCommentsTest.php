<?php

use Illuminate\Auth\GenericUser;
use LoggedCloud\PageStudio\Livewire\PageBuilder;
use LoggedCloud\PageStudio\Models\BlockComment;
use LoggedCloud\PageStudio\Models\Page;
use LoggedCloud\PageStudio\Models\RouteDefinition;
use LoggedCloud\PageStudio\Support\BlockFactory;
use LoggedCloud\PageStudio\Tests\TestCase;

uses(TestCase::class)->in(__FILE__);

/**
 * Build a stub authenticated user. GenericUser is the lightest stand-in
 * that satisfies auth()->user() / auth()->id() in test context, no
 * users table required.
 */
function loginAs(int $id = 1, string $name = 'Reviewer', string $email = 'r@example.com'): GenericUser
{
    $user = new GenericUser([
        'id'    => $id,
        'name'  => $name,
        'email' => $email,
    ]);
    auth()->setUser($user);
    return $user;
}

function freshBuilderOnRoute(string $name = 'r'): PageBuilder
{
    $route = RouteDefinition::create(['name' => $name, 'path_template' => '/x', 'method' => 'get']);
    $pb = new PageBuilder();
    $pb->mount($route->id);
    return $pb;
}

function freshBuilderOnPage(array $blocks = []): array
{
    $page = Page::create(['route_id' => null, 'blocks' => $blocks]);
    $pb = new PageBuilder();
    $pb->mount(null, $page->id);
    return [$pb, $page];
}

it('persists a comment row with the page_id + block_id', function () {
    loginAs();
    [$pb, $page] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];

    $pb->addComment($block['id'], 'Looks good to me');

    $row = BlockComment::first();
    expect($row)->not->toBeNull()
        ->and($row->page_id)->toBe($page->id)
        ->and($row->block_id)->toBe($block['id'])
        ->and($row->body)->toBe('Looks good to me')
        ->and($row->author_id)->toBe(1)
        ->and($row->author_name)->toBe('Reviewer')
        ->and($row->resolved)->toBeFalse();
});

it('refuses to post when no pageId and no routeId is bound (ephemeral mode)', function () {
    loginAs();
    $pb = new PageBuilder();
    $pb->mount();
    $pb->blocks = [BlockFactory::make('heading')];

    $pb->addComment($pb->blocks[0]['id'], 'Hello');

    expect(BlockComment::count())->toBe(0);
});

it('refuses an empty body', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];

    $pb->addComment($block['id'], '   ');
    $pb->addComment($block['id'], '');

    expect(BlockComment::count())->toBe(0);
});

it('threads a reply under its parent', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];
    $pb->addComment($block['id'], 'Top-level note');
    $top = BlockComment::first();

    $pb->replyToComment($top->id, 'Here is a reply');

    $reply = BlockComment::where('parent_id', $top->id)->first();
    expect($reply)->not->toBeNull()
        ->and($reply->body)->toBe('Here is a reply')
        ->and($reply->block_id)->toBe($block['id']);
});

it('flips the resolved flag and reopens it', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];
    $pb->addComment($block['id'], 'Needs work');
    $row = BlockComment::first();

    $pb->resolveComment($row->id);
    expect(BlockComment::find($row->id)->resolved)->toBeTrue()
        ->and(BlockComment::find($row->id)->resolved_at)->not->toBeNull()
        ->and(BlockComment::find($row->id)->resolved_by)->toBe(1);

    $pb->reopenComment($row->id);
    expect(BlockComment::find($row->id)->resolved)->toBeFalse()
        ->and(BlockComment::find($row->id)->resolved_at)->toBeNull()
        ->and(BlockComment::find($row->id)->resolved_by)->toBeNull();
});

it('only allows the author to delete their own comment', function () {
    loginAs(1, 'Author One');
    [$pb] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];
    $pb->addComment($block['id'], 'Mine');
    $row = BlockComment::first();

    // Switch to a different user · delete should be refused.
    loginAs(2, 'Author Two');
    $pb->deleteComment($row->id);
    expect(BlockComment::find($row->id))->not->toBeNull();

    // Back to the author · delete should succeed.
    loginAs(1, 'Author One');
    $pb->deleteComment($row->id);
    expect(BlockComment::find($row->id))->toBeNull();
});

it('groups open comments by block_id with replies nested in order', function () {
    loginAs();
    [$pb, $page] = freshBuilderOnPage();
    $a = BlockFactory::make('heading');
    $b = BlockFactory::make('paragraph');
    $pb->blocks = [$a, $b];

    $pb->addComment($a['id'], 'A1');
    $first = BlockComment::first();
    $pb->replyToComment($first->id, 'A1-reply');
    $pb->addComment($b['id'], 'B1');

    // Resolve one · it should drop out of blockComments.
    $pb->addComment($a['id'], 'A2');
    $a2 = BlockComment::orderBy('id', 'desc')->first();
    $pb->resolveComment($a2->id);

    $grouped = $pb->blockComments();

    expect(array_keys($grouped))->toContain($a['id'], $b['id'])
        ->and($grouped[$a['id']])->toHaveCount(1)
        ->and($grouped[$a['id']][0]['body'])->toBe('A1')
        ->and($grouped[$a['id']][0]['replies'])->toHaveCount(1)
        ->and($grouped[$a['id']][0]['replies'][0]['body'])->toBe('A1-reply')
        ->and($grouped[$b['id']])->toHaveCount(1)
        ->and($grouped[$b['id']][0]['body'])->toBe('B1');
});

it('returns open counts per block, excluding resolved threads', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $a = BlockFactory::make('heading');
    $b = BlockFactory::make('paragraph');
    $pb->blocks = [$a, $b];

    $pb->addComment($a['id'], 'one');
    $pb->addComment($a['id'], 'two');
    $pb->addComment($b['id'], 'three');

    $second = BlockComment::find(2);
    $pb->resolveComment($second->id);

    $counts = $pb->commentsCountByBlock();
    expect($counts[$a['id']] ?? 0)->toBe(1)
        ->and($counts[$b['id']] ?? 0)->toBe(1);
});

/*
 * Duplicate-block behaviour · duplicateBlock() runs cloneBlockWithFreshIds
 * which assigns the clone a brand-new block id. Comments are pinned to
 * the original id, so the duplicate starts as a clean slate · no
 * accidental copy of the source's review history. We assert that here.
 */
it('does not copy comments when a block is duplicated', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $heading = BlockFactory::make('heading');
    $pb->blocks = [$heading];

    $pb->addComment($heading['id'], 'on original');
    $pb->duplicateBlock('0');

    // The clone is now at index 1; its id is freshly generated.
    $cloneId = $pb->blocks[1]['id'];
    expect($cloneId)->not->toBe($heading['id']);

    $counts = $pb->commentsCountByBlock();
    expect($counts[$heading['id']] ?? 0)->toBe(1)
        ->and($counts[$cloneId] ?? null)->toBeNull();
});

/*
 * Block removal does NOT cascade to the comments table. This is
 * intentional for a review workflow · the author may have deleted the
 * block by accident, or may re-add it; in either case losing the
 * reviewer's notes would be the worse failure mode. Orphaned comments
 * stay alive and surface again when a block with the same id reappears.
 */
it('leaves comments alive when the block is removed from the tree', function () {
    loginAs();
    [$pb] = freshBuilderOnPage();
    $heading = BlockFactory::make('heading');
    $pb->blocks = [$heading];
    $pb->addComment($heading['id'], 'still here');

    $pb->removeBlock('0');

    // The block is gone from the tree, but the comment row survives.
    expect($pb->blocks)->toBeEmpty()
        ->and(BlockComment::where('block_id', $heading['id'])->count())->toBe(1);
});

it('captures the author name at save time even if it changes later', function () {
    loginAs(1, 'Original Name');
    [$pb] = freshBuilderOnPage();
    $block = BlockFactory::make('heading');
    $pb->blocks = [$block];
    $pb->addComment($block['id'], 'snapshot');

    // Change the host-app user's display name · the stored row should
    // keep the original name on the comment row.
    loginAs(1, 'Renamed Later');

    $row = BlockComment::first();
    expect($row->author_name)->toBe('Original Name');
});

it('creates a Page row on first comment when only a route is bound', function () {
    loginAs();
    $pb = freshBuilderOnRoute('comment-route');

    // No Page row yet · route bound, page not.
    expect(Page::where('route_id', $pb->routeId)->count())->toBe(0);

    $blockId = 'b_'.bin2hex(random_bytes(4));
    $pb->addComment($blockId, 'first comment');

    $page = Page::where('route_id', $pb->routeId)->first();
    expect($page)->not->toBeNull()
        ->and(BlockComment::where('page_id', $page->id)->count())->toBe(1);
});
