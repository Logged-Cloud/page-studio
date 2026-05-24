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
            ->create($prefix.'node_graphs', function (Blueprint $table) use ($prefix) {
                $table->id();
                // One graph per route · the graph composes the route's
                // variables (and constants / model lookups) into more
                // variables that the page renderer can use.
                $table->foreignId('route_id')
                    ->unique()
                    ->constrained($prefix.'routes')
                    ->cascadeOnDelete();
                // Node + edge lists stored as JSON · graphs stay small enough
                // that nested-set / adjacency tables would be overkill.
                $table->json('nodes');
                $table->json('edges');
                $table->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'node_graphs');
    }
};
