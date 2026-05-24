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
            ->create($prefix.'revisions', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->foreignId('route_id')
                    ->constrained($prefix.'routes')
                    ->cascadeOnDelete();
                $table->json('blocks');
                $table->json('nodes');
                $table->json('edges');
                $table->unsignedBigInteger('author_id')->nullable();
                $table->timestamps();
                $table->index(['route_id', 'created_at']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'revisions');
    }
};
