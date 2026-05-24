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
            ->create($prefix.'variables', function (Blueprint $table) {
                $table->id();
                // Identifier used inside routes / pages (e.g. {userId}). Must be
                // unique so a single variable definition is reusable everywhere.
                $table->string('name')->unique();
                $table->string('label')->nullable();
                $table->string('type'); // int | slug | uuid | alpha | enum | any | custom
                // Custom-type regex (no leading ^ / trailing $); routes wire
                // this into Route::where().
                $table->string('regex')->nullable();
                // Example values · used in previews, Dusk fixtures, and to
                // populate enum constraints.
                $table->json('examples');
                $table->text('description')->nullable();
                $table->timestamps();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'variables');
    }
};
