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
            ->create($prefix.'block_locks', function (Blueprint $t) use ($prefix) {
                $t->id();
                $t->foreignId('page_id')->constrained($prefix.'pages')->cascadeOnDelete();
                // Block ids are short opaque strings minted by BlockFactory · 64 is
                // far more than we need today but leaves headroom if the id scheme
                // ever changes (e.g. uuids).
                $t->string('block_id', 64);
                $t->foreignId('author_id')->nullable();
                $t->string('author_name', 128)->nullable();
                // expires_at is the source of truth for liveness · heartbeats
                // push it forward, releases delete the row, computed reads
                // filter by expires_at > now().
                $t->timestamp('expires_at')->index();
                $t->timestamps();
                // One active lock per block per page · acquireBlockLock relies
                // on this constraint via updateOrCreate semantics.
                $t->unique(['page_id', 'block_id']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'block_locks');
    }
};
