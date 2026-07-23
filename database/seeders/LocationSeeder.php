<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['name' => 'Gedung 1 (Unit 1)', 'description' => 'Area Gedung Unit 1'],
            ['name' => 'Gedung 2 (Unit 2)', 'description' => 'Area Gedung Unit 2'],
            ['name' => 'Gedung 3 (Unit 3)', 'description' => 'Area Gedung Unit 3'],
            ['name' => 'Gedung 4 (Unit 4 / Citra Mart)', 'description' => 'Area Gedung Unit 4'],
            ['name' => 'Gedung 5 (Unit 5 / Perpustakaan / Basement 5)', 'description' => 'Area Gedung Unit 5'],
            ['name' => 'Gedung 6 (Unit 6 / Cinema)', 'description' => 'Area Gedung Unit 6'],
            ['name' => 'Gedung 7 (Unit 7)', 'description' => 'Area Gedung Unit 7'],
            ['name' => 'Basement Parkir Unit 3', 'description' => 'Area Parkir Basement'],
            ['name' => 'Pos Satpam Utama', 'description' => 'Pos Keamanan Satpam'],
        ];

        foreach ($locations as $loc) {
            Location::updateOrCreate(
                ['name' => $loc['name']],
                ['description' => $loc['description']]
            );
        }
    }
}
