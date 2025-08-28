<?php

namespace Database\Seeders;

use AdminSeeder;
use App\Models\User;
use BlockedPatientsSeeder;
use Database\Seeders\AdminSeeder as SeedersAdminSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(\Database\Seeders\AdminSeeder::class);
        $this->call(\Database\Seeders\BlockedPatientsSeeder::class);
    }
}
