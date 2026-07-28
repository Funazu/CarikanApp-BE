<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\AuditLog;
use App\Services\JwtService;
use App\Services\MatchingService;
use App\Services\ImageService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Services\CacheHelper;

class ItemController extends Controller
{
    protected JwtService $jwtService;
    protected MatchingService $matchingService;

    public function __construct(JwtService $jwtService, MatchingService $matchingService)
    {
        $this->jwtService = $jwtService;
        $this->matchingService = $matchingService;
    }

    /**
     * Get the authenticated user from the request optionally (for public routes)
     */
    protected function getOptionalUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }

        $payload = $this->jwtService->decodeToken($token);
        if (!$payload || !isset($payload->sub)) {
            return null;
        }

        return \App\Models\User::find($payload->sub);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentUser = $this->getOptionalUser($request);
        $queryParams = $request->all();
        $userId = $currentUser ? $currentUser->id : 'guest';
        $cacheKey = 'items_index_' . md5(json_encode($queryParams) . '_' . $userId);

        $itemsData = CacheHelper::remember(['items'], $cacheKey, 3600, function () use ($request, $currentUser) {
            $query = Item::query()->with(['user:id,name,email,contact_info,avatar_url', 'category', 'location']);

            // Hide FOUND items with status IN_PROCESS from the public
            // (i.e. users who are not owners and not admins)
            if (!$currentUser || $currentUser->role !== 'ADMIN') {
                $query->where(function ($q) use ($currentUser) {
                    $q->where('type', '!=', 'FOUND')
                      ->orWhere('status', '!=', 'IN_PROCESS');

                    if ($currentUser) {
                        $q->orWhere('user_id', $currentUser->id);
                    }
                });
            }

            // Search text in title or description
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtering
            if ($request->filled('categoryId')) {
                $query->where('category_id', $request->input('categoryId'));
            }

            // Filtering
            if ($request->filled('locationId')) {
                $query->where('location_id', $request->input('locationId'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('startDate') && $request->filled('endDate')) {
                $query->whereBetween('event_time', [
                    $request->input('startDate') . ' 00:00:00',
                    $request->input('endDate') . ' 23:59:59'
                ]);
            }

            // Ordering: Newest first
            $query->latest();

            // Pagination
            $limit = $request->input('limit', 10);
            return $query->paginate($limit)->toArray();
        });

        // Hide secret feature from response for users who are not owners or admins
        $isAdmin = $currentUser && $currentUser->role === 'ADMIN';

        foreach ($itemsData['data'] as &$item) {
            $isOwner = $currentUser && $currentUser->id === $item['user_id'];
            if (!$isOwner && !$isAdmin) {
                unset($item['secret_feature']);
            }
        }

        return response()->json($itemsData, 200);
    }

    /**
     * Store a newly created item (Auth required).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'secret_feature' => ($request->type === 'FOUND' ? 'required|string|max:255' : 'nullable|string|max:255'),
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'type' => 'required|in:LOST,FOUND',
            'event_time' => 'required|date',
            'contact_info' => 'required|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Handle Image upload locally, compressing to WebP
        $file = $request->file('image');
        $filename = ImageService::convertToWebp($file, 'uploads/items');
        $imageUrl = asset('uploads/items/' . $filename);

        $item = Item::create([
            'title' => $request->title,
            'description' => $request->description,
            'secret_feature' => $request->secret_feature ?: 'Tidak ada ciri khusus (Laporan Kehilangan)',
            'image_url' => $imageUrl,
            'type' => $request->type,
            'status' => ($user->role !== 'ADMIN') ? 'IN_PROCESS' : 'OPEN',
            'event_time' => $request->event_time,
            'contact_info' => $request->contact_info,
            'storage_location' => $request->storage_location,
            'user_id' => $user->id,
            'category_id' => $request->category_id,
            'location_id' => $request->location_id,
        ]);

        // Auto Match Suggestion Engine Trigger for LOST items
        if ($item->type === 'LOST') {
            $this->matchingService->findMatchesForLostItem($item);
        }

        CacheHelper::flush(['items']);

        return response()->json([
            'message' => 'Report created successfully',
            'item' => $item
        ], 201);
    }

    /**
     * Display the specified resource (Public).
     */
    public function show(Request $request, $id)
    {
        $itemArray = CacheHelper::remember(['items'], "item_show_{$id}", 3600, function () use ($id) {
            $item = Item::with(['category', 'location', 'user'])->find($id);
            return $item ? $item->toArray() : null;
        });

        if (!$itemArray) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $currentUser = $this->getOptionalUser($request);
        $isOwner = $currentUser && $currentUser->id === $itemArray['user_id'];
        $isAdmin = $currentUser && $currentUser->role === 'ADMIN';

        // Hide FOUND items with status IN_PROCESS from the public
        if ($itemArray['type'] === 'FOUND' && $itemArray['status'] === 'IN_PROCESS' && !$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        if (!$isOwner && !$isAdmin) {
            unset($itemArray['secret_feature']);
        }

        return response()->json($itemArray, 200);
    }

    /**
     * Update the specified resource (Auth required).
     */
    public function update(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $currentUser = auth()->user();
        $isOwner = $currentUser->id === $item->user_id;
        $isAdmin = $currentUser->role === 'ADMIN';

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden - You do not own this report'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'secret_feature' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'status' => 'nullable|in:OPEN,IN_PROCESS,RETURNED',
            'event_time' => 'nullable|date',
            'contact_info' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $item->status;
        $data = $request->only([
            'title', 'description', 'secret_feature', 'event_time', 'contact_info', 'storage_location', 'category_id', 'location_id'
        ]);

        if ($request->has('status')) {
            if ($currentUser->role !== 'ADMIN' && $request->input('status') !== $oldStatus) {
                return response()->json(['message' => 'Forbidden - Hanya Satpam/Admin yang dapat mengubah status laporan barang.'], 403);
            }
            $data['status'] = $request->input('status');
        }

        if ($request->hasFile('image')) {
            // Upload new image, converting and compressing to WebP
            $file = $request->file('image');
            $filename = ImageService::convertToWebp($file, 'uploads/items');
            $data['image_url'] = asset('uploads/items/' . $filename);
        }

        $item->update($data);

        // Audit Logging if status changed
        if ($request->has('status') && $oldStatus !== $item->status) {
            AuditLog::create([
                'item_id' => $item->id,
                'actor_id' => $currentUser->id,
                'action' => 'STATUS_CHANGE',
                'old_value' => $oldStatus,
                'new_value' => $item->status
            ]);

            // Reward +5 Trust Score Points to Finder when status changes to RETURNED
            if ($oldStatus !== 'RETURNED' && $item->status === 'RETURNED' && $item->type === 'FOUND' && $item->user_id) {
                $finder = \App\Models\User::find($item->user_id);
                if ($finder) {
                    $finder->increment('trust_score', 5);

                    \App\Models\Notification::create([
                        'user_id' => $finder->id,
                        'title' => 'Poin Kejujuran Bertambah! (+5)',
                        'message' => "Terima kasih atas kejujuran Anda! Barang temuan '{$item->title}' telah berhasil dikembalikan ke pemiliknya. Anda mendapatkan +5 Poin Kejujuran.",
                        'is_read' => false
                    ]);
                }
            }

            // Notify owner when Satpam verifies physical reception of a LOST item (status changed OPEN -> IN_PROCESS)
            if ($oldStatus === 'OPEN' && $item->status === 'IN_PROCESS' && $item->type === 'LOST' && $item->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $item->user_id,
                    'title' => 'Barang Kehilangan Anda Diverifikasi & Disimpan Satpam!',
                    'message' => "Barang Anda '{$item->title}' telah diverifikasi fisik oleh Satpam dan disimpan di: " . ($item->storage_location ?: 'Pos Satpam') . ". Silakan temui Satpam untuk mengambil barang Anda!",
                    'is_read' => false
                ]);
            }

            // If a FOUND item's status is changed to OPEN, trigger matching for it
            if ($item->type === 'FOUND' && $item->status === 'OPEN') {
                $this->matchingService->findMatchesForFoundItem($item);
            }
        }

        CacheHelper::flush(['items']);

        return response()->json([
            'message' => 'Report updated successfully',
            'item' => $item
        ], 200);
    }

    /**
     * Remove the specified resource (Auth required).
     */
    public function destroy($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $currentUser = auth()->user();
        $isOwner = $currentUser->id === $item->user_id;
        $isAdmin = $currentUser->role === 'ADMIN';

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden - You do not own this report'], 403);
        }

        // Audit Logging for soft delete
        AuditLog::create([
            'item_id' => $item->id,
            'actor_id' => $currentUser->id,
            'action' => 'SOFT_DELETE',
            'old_value' => $item->status,
            'new_value' => 'DELETED'
        ]);

        $item->delete(); // Laravel soft delete

        CacheHelper::flush(['items']);

        return response()->json([
            'message' => 'Report soft-deleted successfully'
        ], 200);
    }

    /**
     * Get audit logs for the item (Owner or Admin only)
     */
    public function logs($id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $currentUser = auth()->user();
        $isOwner = $currentUser->id === $item->user_id;
        $isAdmin = $currentUser->role === 'ADMIN';

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Forbidden - Access restricted to owner or admin'], 403);
        }

        $logs = AuditLog::where('item_id', $id)->with('actor')->orderBy('created_at', 'desc')->get();

        return response()->json($logs, 200);
    }

    /**
     * Report that a LOST item was found and handed to Satpam.
     */
    public function reportFound(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item || $item->type !== 'LOST') {
            return response()->json(['message' => 'Laporan barang kehilangan tidak ditemukan.'], 404);
        }

        if ($item->status !== 'OPEN') {
            return response()->json(['message' => 'Laporan ini sedang diproses atau telah selesai dikembalikan.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'storage_location' => 'required|string|max:255',
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $finder = auth()->user();

        // Keep status OPEN when form is submitted by finder (will become IN_PROCESS after Satpam verifies)
        $oldStatus = $item->status;
        $item->storage_location = $request->storage_location;
        $item->status = 'OPEN';
        $item->save();

        // Create Audit Log
        AuditLog::create([
            'item_id' => $item->id,
            'actor_id' => $finder->id,
            'action' => 'FOUND_REPORTED',
            'old_value' => $oldStatus,
            'new_value' => "Penemu: {$finder->name}. Diserahkan ke Satpam: {$request->storage_location}. Catatan: " . ($request->note ?: '-')
        ]);

        // Notify Admins / Satpam ONLY for physical verification
        $admins = \App\Models\User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->id,
                'title' => 'Penyerahan Barang Kehilangan ke Satpam',
                'message' => "Mahasiswa {$finder->name} menginfokan telah menyerahkan barang '{$item->title}' ke {$request->storage_location}. Mohon verifikasi fisik dan ubah status ke IN_PROCESS.",
                'is_read' => false
            ]);
        }

        CacheHelper::flush(['items']);

        return response()->json([
            'message' => 'Laporan penemuan barang berhasil dikirim ke Satpam! Terima kasih atas kejujuran Anda.',
            'item' => $item
        ], 200);
    }
}
