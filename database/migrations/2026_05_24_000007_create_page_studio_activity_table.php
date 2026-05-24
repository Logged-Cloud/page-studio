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
            ->create($prefix.'activity', function (Blueprint $t) use ($prefix) {
                $t->id();
                $t->foreignId('page_id')->nullable()->constrained($prefix.'pages')->cascadeOnDelete();
                $t->foreignId('route_id')->nullable()->constrained($prefix.'routes')->cascadeOnDelete();
                // 'saved' | 'published' | 'unpublished' | 'comment_added' |
                // 'comment_resolved' | 'lock_acquired'. Free-form string so
                // host apps can extend without another migration.
                $t->string('verb', 32);
                $t->foreignId('author_id')->nullable();
                $t->string('author_name', 128)->nullable();
                // Freeform context · e.g. ['block_id' => 'abc'] for comments
                // or lock events, or ['block_label' => 'Heading'] for nicer
                // summaries.
                $t->json('payload')->nullable();
                $t->timestamps();
                $t->index(['page_id', 'created_at']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'activity');
    }
};
