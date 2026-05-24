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
            ->create($prefix.'snippets', function (Blueprint $t) {
                $t->id();
                $t->string('name')->unique();
                $t->string('label')->nullable();
                $t->string('icon', 8)->default('★');
                // Palette section heading · groups multiple snippets under
                // the same label (e.g. "headers", "footers").
                $t->string('group', 32)->default('snippets');
                // Saved subtree · a single root block with its full
                // children map. Re-hydrated on drop with fresh ids.
                $t->json('block');
                // Optional small preview-context for the snippet's settings
                // so the palette can render a thumbnail/preview later.
                $t->json('preview')->nullable();
                $t->foreignId('author_id')->nullable();
                $t->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'snippets');
    }
};
