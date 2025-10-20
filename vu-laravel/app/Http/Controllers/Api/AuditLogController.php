<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);
        
        $query = AuditLog::with(['user', 'certificate']);
        
        if ($request->has('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        
        if ($request->has('event_category')) {
            $query->where('event_category', $request->event_category);
        }
        
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        
        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }
        
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $logs
        ]);
    }
}

