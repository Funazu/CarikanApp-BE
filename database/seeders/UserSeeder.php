<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Bambang Satpam Utama',
                'email' => 'satpam@amikom.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'ADMIN',
                'trust_score' => 100,
                'contact_info' => '0812-3456-7890 (Pos Satpam Unit 1)',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Satpam+Amikom&background=0D8ABC&color=fff',
            ],
            [
                'name' => 'Fauzun Naja',
                'email' => 'fauzunnaja@students.amikom.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'USER',
                'trust_score' => 45,
                'contact_info' => '0821-9988-7766',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Fauzun+Naja&background=4F46E5&color=fff',
            ],
            [
                'name' => 'Karina Titik',
                'email' => 'karina@students.amikom.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'USER',
                'trust_score' => 30,
                'contact_info' => '0857-1122-3344',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Karina+Titik&background=EC4899&color=fff',
            ],
            [
                'name' => 'Dipta Amikom',
                'email' => 'diptarama@students.amikom.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'USER',
                'trust_score' => 25,
                'contact_info' => '0813-7766-5544',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Dipta+Amikom&background=10B981&color=fff',
            ],
            [
                'name' => 'Ahmad Pemilik',
                'email' => 'pemilik@students.amikom.ac.id',
                'password' => Hash::make('password123'),
                'role' => 'USER',
                'trust_score' => 10,
                'contact_info' => '0896-5544-3322',
                'avatar_url' => 'https://ui-avatars.com/api/?name=Ahmad+Pemilik&background=F59E0B&color=fff',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
