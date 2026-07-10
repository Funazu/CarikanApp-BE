<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MatchingService
{
    /**
     * Find matching FOUND items for a newly created LOST item
     */
    public function findMatchesForLostItem(Item $lostItem): void
    {
        if ($lostItem->type !== 'LOST') {
            return;
        }

        // Search for FOUND items in the same category and location within last 7 days
        $matches = Item::where('type', 'FOUND')
            ->where('status', 'OPEN')
            ->where('category_id', $lostItem->category_id)
            ->where('location_id', $lostItem->location_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $lostUser = $lostItem->user;

        foreach ($matches as $foundItem) {
            $title = "Rekomendasi Temuan Barang!";
            $message = "Kami menemukan barang yang mirip dengan laporan kehilangan Anda '{$lostItem->title}'! Seseorang menemukan '{$foundItem->title}' di lokasi yang sama.";

            // 1. In-App Notification
            Notification::create([
                'user_id' => $lostUser->id,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]);

            // 2. Email Blast (Mocked / Logged via Laravel Mail / Log)
            // We write to Laravel log to represent the email blast
            Log::info("EMAIL SENT TO: {$lostUser->email} | TITLE: {$title} | BODY: {$message}");
            
            try {
                // We can also try sending using Laravel's Mail facade (configured to log or SMTP in .env)
                Mail::raw($message, function ($mail) use ($lostUser, $title) {
                    $mail->to($lostUser->email)
                         ->subject($title);
                });
            } catch (\Exception $e) {
                // If mail is not fully configured, fail silently so request doesn't crash
                Log::warning("Failed sending email to {$lostUser->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Find matching LOST items for a newly approved FOUND item
     */
    public function findMatchesForFoundItem(Item $foundItem): void
    {
        if ($foundItem->type !== 'FOUND' || $foundItem->status !== 'OPEN') {
            return;
        }

        // Search for LOST items in the same category and location within last 7 days
        $matches = Item::where('type', 'LOST')
            ->whereIn('status', ['OPEN', 'IN_PROCESS'])
            ->where('category_id', $foundItem->category_id)
            ->where('location_id', $foundItem->location_id)
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        foreach ($matches as $lostItem) {
            $lostUser = $lostItem->user;
            $title = "Rekomendasi Temuan Barang!";
            $message = "Kami menemukan barang yang mirip dengan laporan kehilangan Anda '{$lostItem->title}'! Seseorang telah menitipkan '{$foundItem->title}' di Pos Satpam.";

            // 1. In-App Notification
            Notification::create([
                'user_id' => $lostUser->id,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
            ]);

            // 2. Email Blast (Mocked / Logged via Laravel Mail / Log)
            Log::info("EMAIL SENT TO: {$lostUser->email} | TITLE: {$title} | BODY: {$message}");
            
            try {
                Mail::raw($message, function ($mail) use ($lostUser, $title) {
                    $mail->to($lostUser->email)
                         ->subject($title);
                });
            } catch (\Exception $e) {
                Log::warning("Failed sending email to {$lostUser->email}: " . $e->getMessage());
            }
        }
    }
}
