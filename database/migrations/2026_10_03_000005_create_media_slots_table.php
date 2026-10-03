<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo spots of the pages (config('cms.slots') + "service.{slug}.cover"): which photo fills each one.
     */
    public function up(): void
    {
        Schema::create('media_slots', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 120)->unique();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_slots');
    }
};
