<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        
        $query = User::query();
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $users = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    public function show($id): JsonResponse
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users|min:3|max:50',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'role' => 'required|in:admin,certificate_manager,regular_user',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
            'phone' => $request->phone,
            'department' => $request->department,
            'is_active' => $request->get('is_active', true),
        ]);

        $this->logAuditEvent('user_created', 'user_management', "User {$user->username} created", [
            'user_id' => $user->id,
            'created_by' => auth()->id()
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'string|unique:users,username,'.$id.'|min:3|max:50',
            'email' => 'email|unique:users,email,'.$id,
            'password' => 'nullable|string|min:8',
            'first_name' => 'string|max:50',
            'last_name' => 'string|max:50',
            'role' => 'in:admin,certificate_manager,regular_user',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'username', 'email', 'first_name', 'last_name', 
            'role', 'phone', 'department', 'is_active'
        ]);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        $this->logAuditEvent('user_updated', 'user_management', "User {$user->username} updated", [
            'user_id' => $user->id,
            'updated_by' => auth()->id()
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 400);
        }

        $username = $user->username;
        $user->delete();

        $this->logAuditEvent('user_deleted', 'user_management', "User {$username} deleted", [
            'user_id' => $id,
            'deleted_by' => auth()->id()
        ], request());

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    private function logAuditEvent(string $eventType, string $category, string $description, array $metadata = [], Request $request = null): void
    {
        AuditLog::create([
            'event_type' => $eventType,
            'event_category' => $category,
            'description' => $description,
            'ip_address' => $request ? $request->ip() : '127.0.0.1',
            'user_agent' => $request ? $request->userAgent() : null,
            'user_id' => auth()->id(),
            'metadata' => $metadata,
            'severity' => 'medium'
        ]);
    }
}

