<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versions (docs/CMS.md §13 F30): the state before and after a change (texts, services, free
     * pages, settings, photo metadata) — App\Cms\Activity::record(..., before:, after:) — so the admin
     * can restore an earlier version from the activity list.
     */
    public function up(): void
    {
        Schema::table('cms_activity', function (Blueprint $table) {
            $table->json('before')->nullable();
            $table->json('after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cms_activity', function (Blueprint $table) {
            $table->dropColumn(['before', 'after']);
        });
    }
};
