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
            ->table($prefix.'pages', function (Blueprint $t) {
                // Free-form key/value bag for per-page metadata · email
                // subject + preheader + reply-to today, room to grow into
                // SEO meta / scheduled-publish flags / locale codes later.
                $t->json('meta')->nullable();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->table($prefix.'pages', function (Blueprint $t) {
                $t->dropColumn('meta');
            });
    }
};
