<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo files of the "database" media driver (App\Cms\Media\DatabaseMediaStorage). The bytes are
     * base64 text: the Supabase pooler runs with emulated prepares, which cannot bind binary strings.
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('mime', 40);
            $table->unsignedInteger('size');
            $table->longText('contents');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
