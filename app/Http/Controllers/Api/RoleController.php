<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (
            ! $actor ||
            ! $actor->hasRole('system_admin') ||
            ! $actor->can('users.assign_roles')
        ) {
            return response()->json([
                'message' => 'Permission denied.',
            ], 403);
        }

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with(['permissions' => function ($query) {
                $query->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        $result = $roles->map(function (Role $role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users()->count(),
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->values()
                    ->all(),
            ];
        });

        return response()->json([
            'roles' => $result->values(),
            'total' => $result->count(),
        ]);
    }
}
