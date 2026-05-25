<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inline edit-lock cursor · the block-lock row gains an optional `field`
 * column that names which input the lock holder is currently focused on
 * (e.g. "Heading text" / "Subheading" / "Link"). Surfaced in the lock
 * ribbon so other reviewers can see at a glance not just WHO is editing
 * but WHICH field.
 *
 * Nullable so legacy rows keep working · the heartbeat / activeBlockLocks
 * paths treat a missing field as "no inline field info available".
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->table($prefix.'block_locks', function (Blueprint $t) {
                $t->string('field', 64)->nullable()->after('author_name');
            });
    }

    public function down(): void
    {
        $prefix = config('page-studio.table_prefix', 'page_studio_');
        Schema::connection(config('page-studio.connection'))
            ->table($prefix.'block_locks', function (Blueprint $t) {
                $t->dropColumn('field');
            });
    }
};
