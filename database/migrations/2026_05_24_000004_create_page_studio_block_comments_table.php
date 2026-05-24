<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');

        Schema::connection(config('page-studio.connection'))
            ->create($prefix.'block_comments', function (Blueprint $t) use ($prefix) {
                $t->id();
                $t->foreignId('page_id')
                    ->constrained($prefix.'pages')
                    ->cascadeOnDelete();
                // The block-tree node id (BlockFactory::make assigns it once
                // and the tree mutations preserve it on move / reorder / nest)
                // not an auto-incrementing PK · this is how a comment binds
                // to a specific block across re-renders.
                $t->string('block_id', 64);
                // Self-reference for threaded replies · top-level threads
                // leave this null, replies point at the thread root.
                $t->foreignId('parent_id')->nullable();
                // Best-effort link back to the host app's user · we also
                // copy the resolved display name so author attribution
                // survives even when the user later renames or is deleted.
                $t->foreignId('author_id')->nullable();
                $t->string('author_name', 128)->nullable();
                $t->text('body');
                $t->boolean('resolved')->default(false);
                $t->timestamp('resolved_at')->nullable();
                $t->foreignId('resolved_by')->nullable();
                $t->timestamps();

                $t->index(['page_id', 'block_id']);
                $t->index(['page_id', 'resolved']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'block_comments');
    }
};
