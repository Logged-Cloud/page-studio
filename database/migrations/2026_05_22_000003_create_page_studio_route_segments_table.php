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
            ->create($prefix.'route_segments', function (Blueprint $table) use ($prefix) {
                $table->id();
                $table->foreignId('route_id')
                    ->constrained($prefix.'routes')
                    ->cascadeOnDelete();
                // 0-indexed position from the left of the URL.
                $table->unsignedInteger('position');
                // 'literal' = fixed text ("users"); 'variable' = {var-id-FK}.
                $table->string('kind', 16);
                $table->string('literal_value')->nullable();
                $table->foreignId('variable_id')
                    ->nullable()
                    ->constrained($prefix.'variables')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['route_id', 'position']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'route_segments');
    }
};
