<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
        'full_name' => 'Admin admin',
        'email' => 'admin@gmail.com',
        'password' => Hash::make('123456000'),
        'date_of_birth' => '1990-1-1',
        'role' => 'admin',
        'gender'=>'male',
        'status' => 'approved',
    ]);
    }
}
