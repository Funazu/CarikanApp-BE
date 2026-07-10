<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Location;
use App\Models\Item;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RedisCachingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = new JwtService();

        // Flush cache before each test
        Cache::flush();

        $this->admin = User::create([
            'name' => 'Admin Satpam',
            'email' => 'admin@amikom.ac.id',
            'password' => bcrypt('password'),
            'role' => 'ADMIN',
        ]);
    }

    private function getAuthHeader(User $user): array
    {
        $token = $this->jwtService->generateAccessToken($user);
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_categories_endpoints_cache_and_invalidate(): void
    {
        // Create initial category
        $category = Category::create(['name' => 'Elektronik']);

        // Assert cache is empty initially
        $this->assertFalse(Cache::tags(['categories'])->has('categories_all'));

        // Request list -> should populate cache
        $response = $this->getJson('/api/categories');
        $response->assertStatus(200);

        $this->assertTrue(Cache::tags(['categories'])->has('categories_all'));

        // Request detail -> should populate cache
        $this->assertFalse(Cache::tags(['categories'])->has("category_show_{$category->id}"));
        $response = $this->getJson('/api/categories/' . $category->id);
        $response->assertStatus(200);

        $this->assertTrue(Cache::tags(['categories'])->has("category_show_{$category->id}"));

        // Create a new category -> should invalidate categories cache
        $this->postJson('/api/categories', [
            'name' => 'Pakaian',
        ], $this->getAuthHeader($this->admin));

        $this->assertFalse(Cache::tags(['categories'])->has('categories_all'));
        $this->assertFalse(Cache::tags(['categories'])->has("category_show_{$category->id}"));
    }

    public function test_locations_endpoints_cache_and_invalidate(): void
    {
        $location = Location::create(['name' => 'Gedung 3']);

        $this->assertFalse(Cache::tags(['locations'])->has('locations_all'));

        $response = $this->getJson('/api/locations');
        $response->assertStatus(200);

        $this->assertTrue(Cache::tags(['locations'])->has('locations_all'));

        // Create new location -> should invalidate
        $this->postJson('/api/locations', [
            'name' => 'Gedung 2',
        ], $this->getAuthHeader($this->admin));

        $this->assertFalse(Cache::tags(['locations'])->has('locations_all'));
    }

    public function test_items_endpoints_cache_and_invalidate(): void
    {
        $category = Category::create(['name' => 'Elektronik']);
        $location = Location::create(['name' => 'Lobby']);

        $item = Item::create([
            'title' => 'Kunci Motor',
            'description' => 'Gantungan kunci coklat',
            'secret_feature' => 'Kunci berlogo Honda',
            'image_url' => 'http://localhost/test.webp',
            'type' => 'LOST',
            'status' => 'OPEN',
            'event_time' => now(),
            'contact_info' => '0812345678',
            'user_id' => $this->admin->id,
            'category_id' => $category->id,
            'location_id' => $location->id,
        ]);

        $cacheKeyShow = "item_show_{$item->id}";

        // Request detail -> caches detail
        $this->assertFalse(Cache::tags(['items'])->has($cacheKeyShow));
        $response = $this->getJson('/api/items/' . $item->id);
        $response->assertStatus(200);
        $this->assertTrue(Cache::tags(['items'])->has($cacheKeyShow));

        // Create a new item -> flushes items tag cache
        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('item.jpg');
        $this->postJson('/api/items', [
            'title' => 'Dompet Kulit',
            'description' => 'Dompet hitam',
            'secret_feature' => 'Ada foto di dalam',
            'image' => $imageFile,
            'type' => 'LOST',
            'event_time' => now()->format('Y-m-d H:i:s'),
            'contact_info' => '0812345678',
            'category_id' => $category->id,
            'location_id' => $location->id,
        ], $this->getAuthHeader($this->admin));

        $this->assertFalse(Cache::tags(['items'])->has($cacheKeyShow));
    }
}
