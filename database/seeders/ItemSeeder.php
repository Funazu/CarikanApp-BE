<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\User;
use App\Models\Category;
use App\Models\Location;
use Carbon\Carbon;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $fauzun = User::where('email', 'fauzun@students.amikom.ac.id')->first();
        $karina = User::where('email', 'karina@students.amikom.ac.id')->first();
        $dipta = User::where('email', 'dipta@students.amikom.ac.id')->first();
        $pemilik = User::where('email', 'pemilik@students.amikom.ac.id')->first();

        $catElektronik = Category::where('name', 'Elektronik')->first();
        $catKartu = Category::where('name', 'Dokumen & Kartu')->first();
        $catKunci = Category::where('name', 'Kunci & Kendaraan')->first();
        $catAksesoris = Category::where('name', 'Aksesoris & Pakaian')->first();

        $locUnit2 = Location::where('name', 'Gedung 2 (Unit 2)')->first();
        $locUnit4 = Location::where('name', 'Gedung 4 (Unit 4 / Citra Mart)')->first();
        $locUnit5 = Location::where('name', 'Gedung 5 (Unit 5 / Perpustakaan / Basement 5)')->first();
        $locBasement = Location::where('name', 'Basement Parkir Unit 3')->first();
        $locUnit6 = Location::where('name', 'Gedung 6 (Unit 6 / Cinema)')->first();

        // 1. Dompet Eiger Hitam (FOUND, IN_PROCESS - Belum di-takeover satpam)
        Item::updateOrCreate(
            ['title' => 'Ditemukan Dompet Leather Hitam Merk Eiger'],
            [
                'description' => 'Dompet kulit hitam merk Eiger ditemukan tertinggal di atas meja kubikel area baca perpustakaan.',
                'secret_feature' => 'Ada stiker kecil Marvel di balik slot kartu komuter & pasfoto ukuran 2x3',
                'image_url' => url('uploads/items/dompet_eiger_hitam.png'),
                'type' => 'FOUND',
                'status' => 'IN_PROCESS',
                'event_time' => Carbon::now()->subHours(2),
                'contact_info' => 'Hubungi penemu via WA: 0821-9988-7766',
                'storage_location' => null, // Belum diisi Satpam
                'user_id' => $fauzun->id,
                'category_id' => $catAksesoris->id,
                'location_id' => $locUnit5->id,
            ]
        );

        // 2. Kunci Motor Honda Vario (FOUND, OPEN - Sudah takeover Satpam)
        Item::updateOrCreate(
            ['title' => 'Kunci Motor Honda Vario dengan Gantungan Merah'],
            [
                'description' => 'Kunci motor Honda ditemukan tersangkut di stop kontak tiang parkiran basement.',
                'secret_feature' => 'Gantungan kain kain merah tulisan REMOVE BEFORE FLIGHT & goresan di pangkal kunci',
                'image_url' => url('uploads/items/kunci_motor_honda.png'),
                'type' => 'FOUND',
                'status' => 'OPEN',
                'event_time' => Carbon::now()->subHours(5),
                'contact_info' => 'Pos Satpam Utama Amikom',
                'storage_location' => 'LOKER-B3',
                'user_id' => $karina->id,
                'category_id' => $catKunci->id,
                'location_id' => $locBasement->id,
            ]
        );

        // 3. Laptop ASUS ROG (FOUND, OPEN - Ada pending claim untuk disimulasikan di video)
        Item::updateOrCreate(
            ['title' => 'Laptop ASUS ROG Silver Tertinggal di Meja Kelas'],
            [
                'description' => 'Laptop gaming Asus ROG warna silver ditemukan di ruang kelas Gedung Unit 2 setelah perkuliahan sesi 2 selesai.',
                'secret_feature' => 'Wallpaper anime Cyberpunk & stiker GitHub warna putih di samping kiri touchpad',
                'image_url' => url('uploads/items/laptop_asus_rog.png'),
                'type' => 'FOUND',
                'status' => 'OPEN',
                'event_time' => Carbon::now()->subDay(),
                'contact_info' => 'Pos Satpam Utama Amikom',
                'storage_location' => 'LOKER-A1',
                'user_id' => $dipta->id,
                'category_id' => $catElektronik->id,
                'location_id' => $locUnit2->id,
            ]
        );

        // 4. Helm Cargloss Retro (LOST, OPEN)
        Item::updateOrCreate(
            ['title' => 'Kehilangan Helm Cargloss Retro Warna Cream'],
            [
                'description' => 'Helm hilang di area parkir Gedung 4 dekat Citra Mart saat jam makan siang.',
                'secret_feature' => 'Visor kaca ada goresan tipis di sebelah kanan & stiker Amikom di bagian belakang helm',
                'image_url' => 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=800&q=80',
                'type' => 'LOST',
                'status' => 'OPEN',
                'event_time' => Carbon::now()->subHours(3),
                'contact_info' => 'Ahmad Pemilik (WA: 0896-5544-3322)',
                'storage_location' => null,
                'user_id' => $pemilik->id,
                'category_id' => $catKunci->id,
                'location_id' => $locUnit4->id,
            ]
        );

        // 5. KTM Amikom (FOUND, RETURNED - Contoh barang yang sukses dikembalikan)
        Item::updateOrCreate(
            ['title' => 'KTM Amikom Informatika a.n. Ahmad Pemilik'],
            [
                'description' => 'Kartu Tanda Mahasiswa ditemukan di area lorong Gedung 6.',
                'secret_feature' => 'NIM 23.11.5432 & ada bekas tekukan di sudut kanan atas kartu',
                'image_url' => 'https://images.unsplash.com/photo-1578574577315-3fbeb0cecdc2?auto=format&fit=crop&w=800&q=80',
                'type' => 'FOUND',
                'status' => 'RETURNED',
                'event_time' => Carbon::now()->subDays(2),
                'contact_info' => 'Pos Satpam Utama Amikom',
                'storage_location' => 'LOKER-C2',
                'user_id' => $karina->id,
                'category_id' => $catKartu->id,
                'location_id' => $locUnit6->id,
            ]
        );
    }
}
