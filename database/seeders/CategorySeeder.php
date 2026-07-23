<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Elektronik',
                'description' => 'Laptop, Handphone, Charger, Earphone, Flashdisk, Powerbank, dll.'
            ],
            [
                'name' => 'Dokumen & Kartu',
                'description' => 'KTM, KTP, SIM, STNK, Buku Kuliah, Sertifikat, Dompet Kartu.'
            ],
            [
                'name' => 'Kunci & Kendaraan',
                'description' => 'Kunci Motor, Kunci Mobil, Helm, Remote Keyless, Aksesoris Kendaraan.'
            ],
            [
                'name' => 'Aksesoris & Pakaian',
                'description' => 'Dompet, Jaket, Jam Tangan, Kacamata, Tas, Topi.'
            ],
            [
                'name' => 'Lainnya',
                'description' => 'Botol Minum (Tumblr), Payung, Alat Tulis, Barang Olahraga.'
            ],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description']]
            );
        }
    }
}
