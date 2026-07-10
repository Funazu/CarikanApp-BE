<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Location;
use App\Models\Item;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageCompressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Category $category;
    private Location $location;
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = new JwtService();

        // Create user
        $this->user = User::create([
            'name' => 'Compression Student',
            'email' => 'compress@students.amikom.ac.id',
            'password' => bcrypt('password'),
            'role' => 'USER',
        ]);

        $this->category = Category::create([
            'name' => 'Elektronik',
        ]);

        $this->location = Location::create([
            'name' => 'Lantai 1',
        ]);
    }

    private function getAuthHeader(User $user): array
    {
        $token = $this->jwtService->generateAccessToken($user);
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_item_image_upload_converts_to_webp(): void
    {
        // Fake a jpeg upload
        $imageFile = UploadedFile::fake()->image('test_image.jpg', 600, 400);

        $payload = [
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru',
            'secret_feature' => 'Stiker apel',
            'image' => $imageFile,
            'type' => 'FOUND',
            'event_time' => now()->format('Y-m-d H:i:s'),
            'contact_info' => '08123456789',
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ];

        $response = $this->postJson('/api/items', $payload, $this->getAuthHeader($this->user));

        $response->assertStatus(201);
        $item = Item::first();
        
        $this->assertNotNull($item);
        // Assert the URL ends with .webp
        $this->assertStringEndsWith('.webp', $item->image_url);

        // Assert the file exists in public path and is indeed saved
        $relativePath = parse_url($item->image_url, PHP_URL_PATH);
        $fullPath = public_path(ltrim($relativePath, '/'));
        
        $this->assertFileExists($fullPath);
        
        // Clean up the created file
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function test_avatar_upload_converts_to_webp(): void
    {
        // Fake a png upload
        $avatarFile = UploadedFile::fake()->image('my_avatar.png', 200, 200);

        $response = $this->putJson('/api/users/me', [
            'avatar' => $avatarFile,
        ], $this->getAuthHeader($this->user));

        $response->assertStatus(200);
        $this->user->refresh();

        $this->assertStringEndsWith('.webp', $this->user->avatar_url);

        $relativePath = parse_url($this->user->avatar_url, PHP_URL_PATH);
        $fullPath = public_path(ltrim($relativePath, '/'));

        $this->assertFileExists($fullPath);

        // Clean up
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    public function test_claim_proof_image_upload_converts_to_webp(): void
    {
        // Create an open item to claim
        $item = Item::create([
            'title' => 'HP iPhone 13 Pro',
            'description' => 'Warna biru',
            'secret_feature' => 'Stiker',
            'image_url' => 'http://localhost/uploads/items/test.webp',
            'type' => 'FOUND',
            'status' => 'OPEN',
            'event_time' => now(),
            'contact_info' => '08123456789',
            'user_id' => User::create([
                'name' => 'Another User',
                'email' => 'another@students.amikom.ac.id',
                'password' => bcrypt('password'),
                'role' => 'USER',
            ])->id,
            'category_id' => $this->category->id,
            'location_id' => $this->location->id,
        ]);

        $proofImage = UploadedFile::fake()->image('proof.gif', 400, 300);

        $response = $this->postJson('/api/claims', [
            'item_id' => $item->id,
            'proof_description' => 'Saya pemiliknya ini fotonya',
            'proof_image' => $proofImage,
        ], $this->getAuthHeader($this->user));

        $response->assertStatus(201);
        
        $claim = \App\Models\Claim::first();
        $this->assertNotNull($claim);
        $this->assertStringEndsWith('.webp', $claim->proof_image_url);

        $relativePath = parse_url($claim->proof_image_url, PHP_URL_PATH);
        $fullPath = public_path(ltrim($relativePath, '/'));

        $this->assertFileExists($fullPath);

        // Clean up
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
