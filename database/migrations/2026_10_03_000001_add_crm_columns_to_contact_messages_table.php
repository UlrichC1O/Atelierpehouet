<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin CRM (docs/CMS.md §3, §7.2): a pipeline status and internal notes per request.
     *
     * App\Http\Middleware\EnsureCmsReady opens the admin screens only once every 2026_10_03_*
     * migration ran, which therefore also guarantees these columns.
     */
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'notes']);
        });
    }
};
