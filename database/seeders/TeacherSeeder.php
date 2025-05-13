<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Hash;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::create([
            'name' => "Teacher",
            'email' => 't@edunexus.com',
            'password' => Hash::make("12345678"),
            'role' => "teacher",
            'Location'=> 'Dhaka'
        ]);
    }
}
