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
            ->create($prefix.'pages', function (Blueprint $table) use ($prefix) {
                $table->id();
                // Route-bound pages link to a route row · null means a
                // standalone page (page-builder used purely for content,
                // variables passed in by the host app). Indexed so
                // updateOrCreate by route_id stays fast; uniqueness is
                // enforced at the application layer in PageBuilder::save().
                $table->foreignId('route_id')
                    ->nullable()
                    ->constrained($prefix.'routes')
                    ->cascadeOnDelete();
                $table->index('route_id');
                // Authored block tree · stored as JSON so the editor can
                // round-trip without an N+1 nested-set headache.
                $table->json('blocks');
                $table->string('layout')->nullable();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'pages');
    }
};
