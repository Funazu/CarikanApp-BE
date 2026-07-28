<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Claim;
use App\Models\Item;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Services\ImageService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ClaimController extends Controller
{
    /**
     * Submit a claim for an item (Auth required)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:items,id',
            'proof_description' => 'required|string',
            'proof_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $currentUser = auth()->user();
        $item = Item::find($request->item_id);

        // Cannot claim soft deleted items or non-open items
        if ($item->status !== 'OPEN') {
            return response()->json([
                'message' => 'This item is no longer open for claims (Current status: ' . $item->status . ')'
            ], 400);
        }

        // Cannot submit claims for LOST items
        if ($item->type === 'LOST') {
            return response()->json([
                'message' => 'Laporan barang hilang tidak dapat diklaim. Silakan hubungi pelapor langsung melalui kontak yang tertera.'
            ], 400);
        }

        // Cannot claim own item
        if ($item->user_id === $currentUser->id) {
            return response()->json([
                'message' => 'You cannot submit a claim for your own reported item.'
            ], 400);
        }

        // Unique check
        $existingClaim = Claim::where('item_id', $item->id)
            ->where('claimer_id', $currentUser->id)
            ->first();

        if ($existingClaim) {
            return response()->json([
                'message' => 'You have already submitted a claim for this item.'
            ], 400);
        }

        $proofImageUrl = null;
        if ($request->hasFile('proof_image')) {
            $file = $request->file('proof_image');
            $filename = ImageService::convertToWebp($file, 'uploads/claims');
            $proofImageUrl = asset('uploads/claims/' . $filename);
        }

        $claim = Claim::create([
            'item_id' => $item->id,
            'claimer_id' => $currentUser->id,
            'proof_description' => $request->proof_description,
            'proof_image_url' => $proofImageUrl,
            'status' => 'PENDING',
        ]);

        // Notify item owner
        Notification::create([
            'user_id' => $item->user_id,
            'title' => 'Pengajuan Klaim Baru',
            'message' => "Seseorang telah mengajukan klaim atas barang temuan/hilang Anda '{$item->title}'. Sila periksa deskripsi ciri rahasia yang diajukan."
        ]);

        return response()->json([
            'message' => 'Claim submitted successfully',
            'claim' => $claim
        ], 201);
    }

    /**
     * Get incoming claims for items created by the logged-in user (Auth required)
     */
    public function incoming()
    {
        $currentUser = auth()->user();

        if ($currentUser->role === 'ADMIN') {
            // Satpam/Admin gets all claims in the system
            $claims = Claim::with(['item', 'claimer'])->orderBy('created_at', 'desc')->get();
        } else {
            // Get claims on items owned by current user
            $claims = Claim::whereHas('item', function ($query) use ($currentUser) {
                $query->where('user_id', $currentUser->id);
            })->with(['item', 'claimer'])->orderBy('created_at', 'desc')->get();
        }

        return response()->json($claims, 200);
    }

    /**
     * Get claims made by the logged-in user (Auth required)
     */
    public function outgoing()
    {
        $currentUser = auth()->user();

        $claims = Claim::where('claimer_id', $currentUser->id)
            ->with('item')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($claims, 200);
    }

    /**
     * Respond to a claim (Auth required: only owner of the item or admin)
     */
    public function respond(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:APPROVED,REJECTED',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $claim = Claim::with('item')->find($id);

        if (!$claim) {
            return response()->json(['message' => 'Claim not found'], 404);
        }

        $currentUser = auth()->user();
        $item = $claim->item;

        $isAdmin = $currentUser->role === 'ADMIN';

        if (!$isAdmin) {
            return response()->json(['message' => 'Forbidden - Hanya Satpam/Admin yang memiliki wewenang untuk menyetujui klaim barang.'], 403);
        }

        if ($claim->status !== 'PENDING') {
            return response()->json(['message' => 'Klaim ini sudah diselesaikan sebelumnya (Status saat ini: ' . $claim->status . ')'], 400);
        }

        $newStatus = $request->input('status');
        $claim->status = $newStatus;
        $claim->save();

        if ($newStatus === 'APPROVED') {
            $oldItemStatus = $item->status;
            
            // Update item status to RETURNED
            $item->status = 'RETURNED';
            $item->save();

            // Reward +5 Trust Score Points to Finder when claim is approved on a FOUND item
            if ($oldItemStatus !== 'RETURNED' && $item->type === 'FOUND' && $item->user_id) {
                $finder = \App\Models\User::find($item->user_id);
                if ($finder) {
                    $finder->increment('trust_score', 5);

                    Notification::create([
                        'user_id' => $finder->id,
                        'title' => 'Poin Kejujuran Bertambah! (+5)',
                        'message' => "Terima kasih atas kejujuran Anda! Barang temuan '{$item->title}' telah berhasil dikembalikan ke pemiliknya. Anda mendapatkan +5 Poin Kejujuran."
                    ]);
                }
            }

            \App\Services\CacheHelper::flush(['items']);

            // Auto-reject other pending claims for this item
            Claim::where('item_id', $item->id)
                ->where('id', '!=', $claim->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'REJECTED']);

            // Create Audit Log for status change
            AuditLog::create([
                'item_id' => $item->id,
                'actor_id' => $currentUser->id,
                'action' => 'STATUS_CHANGE',
                'old_value' => $oldItemStatus,
                'new_value' => 'RETURNED'
            ]);

            // Notify claimer
            Notification::create([
                'user_id' => $claim->claimer_id,
                'title' => 'Klaim Disetujui!',
                'message' => "Klaim Anda untuk barang '{$item->title}' telah disetujui oleh Satpam! Anda sekarang dapat mengambil barang tersebut di Pos Satpam."
            ]);

            // Notify original reporter/owner
            Notification::create([
                'user_id' => $item->user_id,
                'title' => 'Barang Berhasil Dikembalikan',
                'message' => "Laporan barang '{$item->title}' Anda telah selesai diproses dan diserahkan kepada pemilik oleh Satpam."
            ]);
        } else {
            // Notify claimer
            Notification::create([
                'user_id' => $claim->claimer_id,
                'title' => 'Klaim Ditolak',
                'message' => "Klaim Anda untuk barang '{$item->title}' ditolak oleh Satpam."
            ]);
        }

        return response()->json([
            'message' => "Claim {$newStatus} successfully",
            'claim' => $claim
        ], 200);
    }

    /**
     * Reveal reporter contact info for approved claims (Auth required)
     */
    public function contact($id)
    {
        $claim = Claim::with(['item.user'])->find($id);

        if (!$claim) {
            return response()->json(['message' => 'Claim not found'], 404);
        }

        $currentUser = auth()->user();
        $isClaimer = $currentUser->id === $claim->claimer_id;
        $isAdmin = $currentUser->role === 'ADMIN';

        if (!$isClaimer && !$isAdmin) {
            return response()->json(['message' => 'Forbidden - You did not submit this claim'], 403);
        }

        if ($claim->status !== 'APPROVED') {
            return response()->json([
                'message' => 'Access to contact details is restricted. The claim must be APPROVED first.'
            ], 403);
        }

        $itemOwner = $claim->item->user;

        return response()->json([
            'message' => 'Contact details retrieved successfully',
            'contact_person' => $itemOwner->name,
            'contact_email' => $itemOwner->email,
            'contact_whatsapp_telegram_default' => $itemOwner->contact_info,
            'report_specific_contact' => $claim->item->contact_info,
        ], 200);
    }
}
