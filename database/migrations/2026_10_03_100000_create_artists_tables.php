<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artist pages (docs/ARTISTS.md §3): the artists, their artworks and their exhibitions.
     *
     * Photos are rows of the CMS library: the "media" table of the CMS migrations
     * (2026_10_03_0000NN_*), which run first. SQLite resolves a foreign key when rows are written, so
     * the constraint is always declared there; another driver gets it when "media" exists (otherwise a
     * plain indexed column: a deleted photo then leaves an id that simply resolves to no photo).
     */
    public function up(): void
    {
        $constrained = $this->canReferenceMedia();

        Schema::create('artists', function (Blueprint $table) use ($constrained): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 120);
            $table->string('discipline_fr', 120)->nullable();
            $table->string('discipline_en', 120)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('statement_fr', 400)->nullable();
            $table->string('statement_en', 400)->nullable();
            $table->text('bio_fr')->nullable();
            $table->text('bio_en')->nullable();
            $table->string('meta_fr', 170)->nullable();
            $table->string('meta_en', 170)->nullable();
            $this->mediaColumn($table, 'portrait_media_id', $constrained);
            $table->string('accent', 10)->default('yellow');
            $table->string('website', 255)->nullable();
            $table->string('instagram', 255)->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_example')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('artworks', function (Blueprint $table) use ($constrained): void {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $this->mediaColumn($table, 'media_id', $constrained);
            $table->string('title_fr', 160);
            $table->string('title_en', 160)->nullable();
            $table->string('year', 20)->nullable();
            $table->string('medium_fr', 160)->nullable();
            $table->string('medium_en', 160)->nullable();
            $table->string('dimensions', 80)->nullable();
            $table->text('description_fr')->nullable();
            $table->text('description_en')->nullable();
            $table->string('availability', 20)->default('none');
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['artist_id', 'position']);
        });

        Schema::create('exhibitions', function (Blueprint $table) use ($constrained): void {
            $table->id();
            $table->foreignId('artist_id')->constrained('artists')->cascadeOnDelete();
            $this->mediaColumn($table, 'media_id', $constrained);
            $table->string('title_fr', 160);
            $table->string('title_en', 160)->nullable();
            $table->string('kind', 20)->default('group');
            $table->string('venue', 160)->nullable();
            $table->string('city', 120)->nullable();
            $table->unsignedSmallInteger('year');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('description_fr')->nullable();
            $table->text('description_en')->nullable();
            $table->string('url', 255)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['artist_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitions');
        Schema::dropIfExists('artworks');
        Schema::dropIfExists('artists');
    }

    /** A nullable photo id: foreign key to media(id), set to null when the photo is deleted. */
    private function mediaColumn(Blueprint $table, string $column, bool $constrained): void
    {
        $definition = $table->foreignId($column)->nullable()->index();

        if ($constrained) {
            $definition->constrained('media')->nullOnDelete();
        }
    }

    private function canReferenceMedia(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite' || Schema::hasTable('media');
    }
};
