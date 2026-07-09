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
}
