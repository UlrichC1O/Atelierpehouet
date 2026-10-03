<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The photo library: one row per photo (its files live in the storage named by "driver",
     * under the keys {ulid}.{extension} and {ulid}-{width}.{extension} for the variants).
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->string('driver', 20);
            $table->string('mime', 40);
            $table->string('extension', 5);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size');
            $table->string('original_name', 255)->nullable();
            // list of {width, height, size, key}, ascending, the main file excluded
            $table->json('variants');
            $table->string('alt_fr', 300)->nullable();
            $table->string('alt_en', 300)->nullable();
            $table->string('caption_fr', 500)->nullable();
            $table->string('caption_en', 500)->nullable();
            $table->unsignedTinyInteger('focal_x')->default(50);
            $table->unsignedTinyInteger('focal_y')->default(50);
            $table->string('service_slug', 80)->nullable()->index();
            $table->boolean('in_gallery')->default(false)->index();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
