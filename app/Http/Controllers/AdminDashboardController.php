<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\Claim;

class AdminDashboardController extends Controller
{
    /**
     * Get dashboard statistics (Admin only)
     */
    public function stats()
    {
        $activeLost = Item::where('type', 'LOST')
            ->whereIn('status', ['OPEN', 'IN_PROCESS'])
            ->count();

        $activeFound = Item::where('type', 'FOUND')
            ->whereIn('status', ['OPEN', 'IN_PROCESS'])
            ->count();

        $totalReports = Item::count();
        $returnedReports = Item::where('status', 'RETURNED')->count();
        
        $returnedPercentage = $totalReports > 0 
            ? round(($returnedReports / $totalReports) * 100, 2) 
            : 0;

        $pendingClaims = Claim::where('status', 'PENDING')->count();

        return response()->json([
            'statistics' => [
                'active_lost_reports' => $activeLost,
                'active_found_reports' => $activeFound,
                'returned_reports' => $returnedReports,
                'total_reports' => $totalReports,
                'returned_percentage' => $returnedPercentage,
                'pending_claims_count' => $pendingClaims
            ]
        ], 200);
    }

    /**
     * Get list of users with trust score and report counts (Admin only)
     */
    public function users(Request $request)
    {
        $query = \App\Models\User::withCount(['items', 'claims']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('contact_info', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('role') && in_array($request->role, ['ADMIN', 'USER'])) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('trust_score', 'desc')->get();

        return response()->json($users, 200);
    }

    /**
     * Update user trust score or role (Admin only)
     */
    public function updateUser(Request $request, $id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'trust_score' => 'nullable|integer|min:0',
            'role' => 'nullable|in:ADMIN,USER',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('trust_score')) {
            $user->trust_score = (int) $request->trust_score;
        }

        if ($request->has('role')) {
            $user->role = $request->role;
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ], 200);
    }
}
