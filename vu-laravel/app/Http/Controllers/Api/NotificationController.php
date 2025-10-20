<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        
        $query = Notification::where('user_id', auth()->id());
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function markAsRead($id): JsonResponse
    {
        $notification = Notification::where('id', $id)
                                    ->where('user_id', auth()->id())
                                    ->first();
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        $notification->update([
            'status' => 'read',
            'read_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }
}

