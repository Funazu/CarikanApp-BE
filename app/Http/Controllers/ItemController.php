<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\AuditLog;
use App\Services\JwtService;
use App\Services\MatchingService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
        $query = Item::query()->with(['category', 'location']);

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

        // Pagination
        $limit = $request->input('limit', 10);
        $items = $query->paginate($limit);

        // Hide secret feature from response for users who are not owners or admins
        $currentUser = $this->getOptionalUser($request);

        $items->getCollection()->transform(function ($item) use ($currentUser) {
            $isOwner = $currentUser && $currentUser->id === $item->user_id;
            $isAdmin = $currentUser && $currentUser->role === 'ADMIN';

            if (!$isOwner && !$isAdmin) {
                unset($item->secret_feature);
            }
            return $item;
        });

        return response()->json($items, 200);
    }

    /**
     * Store a newly created item (Auth required).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'secret_feature' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'type' => 'required|in:LOST,FOUND',
            'event_time' => 'required|date',
            'contact_info' => 'required|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'location_id' => 'required|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Handle Image upload locally
        $file = $request->file('image');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('uploads/items'), $filename);
        $imageUrl = asset('uploads/items/' . $filename);

        $item = Item::create([
            'title' => $request->title,
            'description' => $request->description,
            'secret_feature' => $request->secret_feature,
            'image_url' => $imageUrl,
            'type' => $request->type,
            'status' => ($request->type === 'FOUND' && $user->role !== 'ADMIN') ? 'IN_PROCESS' : 'OPEN',
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
        $item = Item::with(['category', 'location', 'user'])->find($id);

        if (!$item) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $currentUser = $this->getOptionalUser($request);
        $isOwner = $currentUser && $currentUser->id === $item->user_id;
        $isAdmin = $currentUser && $currentUser->role === 'ADMIN';

        if (!$isOwner && !$isAdmin) {
            unset($item->secret_feature);
        }

        return response()->json($item, 200);
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'status' => 'nullable|in:OPEN,IN_PROCESS,RETURNED',
            'event_time' => 'nullable|date',
            'contact_info' => 'nullable|string|max:100',
            'storage_location' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
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
            // Upload new image
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/items'), $filename);
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
        }

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
}
