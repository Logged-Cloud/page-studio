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
                // Draft / Published lifecycle · drafts only render to authors,
                // scheduled pages flip to published once `publish_at` passes.
                $t->string('status', 16)->default('draft')->index();
                $t->timestamp('publish_at')->nullable()->index();
                $t->timestamp('published_at')->nullable();
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        $conn   = config('page-studio.connection');

        // SQLite refuses to drop a column while an index references it · drop
        // the two indexes in their own ALTER, then the columns.
        Schema::connection($conn)
            ->table($prefix.'pages', function (Blueprint $t) use ($prefix) {
                $t->dropIndex($prefix.'pages_status_index');
                $t->dropIndex($prefix.'pages_publish_at_index');
            });
        Schema::connection($conn)
            ->table($prefix.'pages', function (Blueprint $t) {
                $t->dropColumn(['status', 'publish_at', 'published_at']);
            });
    }
};
