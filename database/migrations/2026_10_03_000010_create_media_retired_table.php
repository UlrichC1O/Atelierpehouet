<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files of replaced or deleted photos, still served by GET /media/{key} for a few minutes (pages
     * cached with the old URLs keep working), then purged by a later photo write
     * (App\Cms\Media\RetiredFiles, docs/CMS.md §13 C12).
     */
    public function up(): void
    {
        if (Schema::hasTable('media_retired')) {
            return;
        }

        Schema::create('media_retired', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('driver', 20);
            $table->timestamp('retired_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_retired');
    }
};
