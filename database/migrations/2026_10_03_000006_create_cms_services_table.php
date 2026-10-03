<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Services as edited in the CMS: overrides of resources/content/services/{slug}.php (content diff,
     * meta, order, visibility) and the services created in the CMS ("is_custom", full content).
     */
    public function up(): void
    {
        Schema::create('cms_services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('position')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('accent', 40)->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('art_style', 40)->nullable();
            // {"fr": {…content keys…}, "en": {…}}
            $table->json('content')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_services');
    }
};
