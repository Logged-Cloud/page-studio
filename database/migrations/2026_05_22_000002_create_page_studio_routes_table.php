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
            ->create($prefix.'routes', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                // GET / POST / PUT / PATCH / DELETE.
                $table->string('method', 8)->default('GET');
                // Compiled Laravel route pattern, e.g. "/users/{userId}/posts/{slug}".
                $table->string('path_template');
                $table->text('description')->nullable();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'routes');
    }
};
