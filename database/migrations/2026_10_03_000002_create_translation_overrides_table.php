<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Texts edited in the CMS: one row per changed leaf of a lang/{locale}/{group}.php file
     * (the files stay the defaults, see App\Cms\OverridingTranslationLoader).
     */
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('group', 40);
            $table->string('key', 190);
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};
