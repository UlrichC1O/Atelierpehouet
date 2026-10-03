<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The explicit right to use the admin CMS (docs/CMS.md §13 D18, Gate "admin").
     *
     * Every account that exists before this migration was created as an administrator (the admin is
     * the only login of the site, e.g. production's owner, user #1): all of them keep their access.
     * Accounts created later are administrators only when created as such (atelier:admin, the
     * first-login bootstrap, "Ajouter un administrateur").
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });

        DB::table('users')->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
