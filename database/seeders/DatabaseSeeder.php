<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The site's content (services, gallery, animations) lives in files, not in the
     * database, and contact messages only ever come from real visitors: nothing to seed.
     */
    public function run(): void
    {
        //
    }
}
