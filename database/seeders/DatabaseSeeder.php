<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Staff accounts are not seeded (no default credentials). Create the first
     * administrator with: php artisan cms:create-user
     */
    public function run(): void
    {
        $this->call(CmsSeeder::class);
    }
}
