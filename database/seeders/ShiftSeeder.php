<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Shift::updateOrCreate(
            ['name' => 'Morning'],
            [
                'start_time' => '08:00:00',
                'end_time' => '14:00:00',
            ]
        );

        Shift::updateOrCreate(
            ['name' => 'Evening'],
            [
                'start_time' => '14:00:00',
                'end_time' => '20:00:00',
            ]
        );
    }
    }
