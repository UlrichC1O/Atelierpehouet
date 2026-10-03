<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free pages created in the CMS (legal notice, news…), served at /{slug} with a Markdown body.
     */
    public function up(): void
    {
        Schema::create('custom_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title_fr', 120);
            $table->string('title_en', 120)->nullable();
            $table->text('body_fr')->nullable();
            $table->text('body_en')->nullable();
            $table->string('meta_fr', 170)->nullable();
            $table->string('meta_en', 170)->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->boolean('in_footer')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_pages');
    }
};
