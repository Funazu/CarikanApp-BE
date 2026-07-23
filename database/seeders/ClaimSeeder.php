<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Claim;
use App\Models\Item;
use App\Models\User;

class ClaimSeeder extends Seeder
{
    public function run(): void
    {
        $pemilik = User::where('email', 'pemilik@students.amikom.ac.id')->first();
        $laptopItem = Item::where('title', 'LIKE', '%Laptop ASUS ROG%')->first();
        $ktmItem = Item::where('title', 'LIKE', '%KTM Amikom%')->first();

        if ($pemilik && $laptopItem) {
            Claim::updateOrCreate(
                [
                    'item_id' => $laptopItem->id,
                    'claimer_id' => $pemilik->id,
                ],
                [
                    'proof_description' => 'Halo Pak Satpam, saya merasa laptop Asus ROG silver tersebut milik saya yang tertinggal saat kuliah sesi 2 kemarin. Ciri khususnya: wallpaper laptop saya gambar anime Cyberpunk dan ada stiker GitHub warna putih di sebelah kiri touchpad.',
                    'proof_image_url' => null,
                    'status' => 'PENDING',
                ]
            );
        }

        if ($pemilik && $ktmItem) {
            Claim::updateOrCreate(
                [
                    'item_id' => $ktmItem->id,
                    'claimer_id' => $pemilik->id,
                ],
                [
                    'proof_description' => 'KTM atas nama Ahmad Pemilik dengan NIM 23.11.5432. Kartu tertinggal pas wudhu dan di pojok kanan atas ada bekas tekukan sedikit.',
                    'proof_image_url' => null,
                    'status' => 'APPROVED',
                ]
            );
        }
    }
}
