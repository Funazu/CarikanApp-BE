<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Location;
use App\Models\Item;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherUser;
    private User $admin;
    private Category $category;
    private Location $location;
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService();

        // Create Users
        $this->owner = User::create([
            'name' => 'Owner Student',
            'email' => 'owner@students.amikom.ac.id',
            'password' => bcrypt('password'),
            'role' => 'USER',
        ]);

        $this->otherUser = User::create([
            'name' => 'Other Student',
            'email' => 'other@students.amikom.ac.id',
            'password' => bcrypt('password'),
            'role' => 'USER',
        ]);

        $this->admin = User::create([
            'name' => 'Satpam Admin',
            'email' => 'satpam@amikom.ac.id',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
        ]);

        // Create Master Data
        $this->category = Category::create([
            'name' => 'Elektronik',
        ]);

        $this->location = Location::create([
            'name' => 'Gedung 3 Lantai 2',
        ]);
    }

    private function getAuthHeader(User $user): array
    {
        $token = $this->jwtService->generateAccessToken($user);
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_student_creates_found_item_has_in_process_status(): void
    {
        $payload = [
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru pasifik, retak tipis di pojok kanan bawah',
            'secret_feature' => 'Ada stiker apel digigit sebagian',
            'image' => \Illuminate\Http\UploadedFile::fake()->image('iphone.jpg'),
            'type' => 'FOUND',
            'event_time' => now()->format('Y-m-d H:i:s'),
            'contact_info' => '08123456789',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ];

        $response = $this->postJson('/api/items', $payload, $this->getAuthHeader($this->owner));

        $response->assertStatus(201);
        $this->assertDatabaseHas('items', [
            'title' => 'HP iPhone 13 Pro',
            'status' => 'IN_PROCESS',
        ]);
    }

    public function test_unverified_found_item_is_hidden_from_public_list(): void
    {
        $item = Item::create([
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru',
            'secret_feature' => 'Stiker apel',
            'image_url' => 'http://localhost/iphone.jpg',
            'type' => 'FOUND',
            'status' => 'IN_PROCESS',
            'event_time' => now(),
            'contact_info' => '08123456789',
            'user_id' => $this->owner->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        // 1. Guest (unauthenticated) - should not see it
        $response = $this->getJson('/api/items');
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // 2. Another student - should not see it
        $response = $this->getJson('/api/items', $this->getAuthHeader($this->otherUser));
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // 3. Owner student - should see it
        $response = $this->getJson('/api/items', $this->getAuthHeader($this->owner));
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $item->id);

        // 4. Admin/Satpam - should see it
        $response = $this->getJson('/api/items', $this->getAuthHeader($this->admin));
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_unverified_found_item_is_hidden_from_public_show(): void
    {
        $item = Item::create([
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru',
            'secret_feature' => 'Stiker apel',
            'image_url' => 'http://localhost/iphone.jpg',
            'type' => 'FOUND',
            'status' => 'IN_PROCESS',
            'event_time' => now(),
            'contact_info' => '08123456789',
            'user_id' => $this->owner->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        // 1. Guest (unauthenticated) - 404
        $response = $this->getJson('/api/items/' . $item->id);
        $response->assertStatus(404);

        // 2. Another student - 404
        $response = $this->getJson('/api/items/' . $item->id, $this->getAuthHeader($this->otherUser));
        $response->assertStatus(404);

        // 3. Owner student - 200
        $response = $this->getJson('/api/items/' . $item->id, $this->getAuthHeader($this->owner));
        $response->assertStatus(200);

        // 4. Admin/Satpam - 200
        $response = $this->getJson('/api/items/' . $item->id, $this->getAuthHeader($this->admin));
        $response->assertStatus(200);
    }

    public function test_admin_takeover_changes_status_to_open_and_makes_it_public(): void
    {
        $item = Item::create([
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru',
            'secret_feature' => 'Stiker apel',
            'image_url' => 'http://localhost/iphone.jpg',
            'type' => 'FOUND',
            'status' => 'IN_PROCESS',
            'event_time' => now(),
            'contact_info' => '08123456789',
            'user_id' => $this->owner->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        // Satpam (Admin) updates status to OPEN and sets storage location
        $response = $this->putJson('/api/items/' . $item->id, [
            'status' => 'OPEN',
            'storage_location' => 'Laci Pos Satpam Gedung 3',
        ], $this->getAuthHeader($this->admin));

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'status' => 'OPEN',
            'storage_location' => 'Laci Pos Satpam Gedung 3',
        ]);

        // Now guest can view it in list and show
        $response = $this->getJson('/api/items');
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/items/' . $item->id);
        $response->assertStatus(200);
    }

    public function test_matching_notifications_sent_on_takeover(): void
    {
        // 1. Create a LOST item first
        $lostItem = Item::create([
            'title' => 'HP Hilang',
            'description' => 'Mencari iPhone 13 saya',
            'secret_feature' => 'Stiker apel',
            'image_url' => 'http://localhost/iphone.jpg',
            'type' => 'LOST',
            'status' => 'OPEN',
            'event_time' => now(),
            'contact_info' => '08987654321',
            'user_id' => $this->otherUser->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        // 2. Create a FOUND item (it starts as IN_PROCESS, which should NOT match/notify yet)
        $foundItem = Item::create([
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Ditemukan iPhone biru',
            'secret_feature' => 'Stiker apel',
            'image_url' => 'http://localhost/iphone.jpg',
            'type' => 'FOUND',
            'status' => 'IN_PROCESS',
            'event_time' => now(),
            'contact_info' => '08123456789',
            'user_id' => $this->owner->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->otherUser->id,
        ]);

        // 3. Satpam (Admin) updates status to OPEN
        $this->putJson('/api/items/' . $foundItem->id, [
            'status' => 'OPEN',
            'storage_location' => 'Pos Satpam',
        ], $this->getAuthHeader($this->admin));

        // 4. Verify that otherUser (owner of the LOST item) receives notification
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->otherUser->id,
            'title' => 'Rekomendasi Temuan Barang!',
        ]);
    }
}
