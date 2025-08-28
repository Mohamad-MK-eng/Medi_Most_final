<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run()
    {
        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => 'admin',
                'description' => 'System Administrator'
            ],
            [
                'id' => 3,
                'name' => 'secretary',
                'description' => 'Clinic Secretary'
            ],
            [
                'id' => 2,
                'name' => 'doctor',
                'description' => 'Medical Doctor'
            ],
            [
                'id' => 4,
                'name' => 'patient',
                'description' => 'Patient'
            ]
        ]);
    }
}
