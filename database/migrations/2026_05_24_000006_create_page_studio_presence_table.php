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
            ->create($prefix.'presence', function (Blueprint $t) use ($prefix) {
                $t->id();
                $t->foreignId('page_id')->constrained($prefix.'pages')->cascadeOnDelete();
                $t->foreignId('author_id')->nullable();
                $t->string('author_name', 128)->nullable();
                // session_id is the Livewire component instance id · gives
                // a stable handle per browser tab without depending on the
                // host app's session driver.
                $t->string('session_id', 64);
                $t->timestamp('seen_at')->index();
                $t->timestamps();
                $t->unique(['page_id', 'session_id']);
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->dropIfExists($prefix.'presence');
    }
};
