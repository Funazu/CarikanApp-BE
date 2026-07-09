<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * Display a listing of user notifications.
     */
    public function index()
    {
        $user = auth()->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications, 200);
    }

    /**
     * Mark the specified notification as read.
     */
    public function markAsRead($id)
    {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        $notification->is_read = true;
        $notification->save();

        return response()->json([
            'message' => 'Notification marked as read successfully',
            'notification' => $notification
        ], 200);
    }
}
