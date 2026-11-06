<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class RegistrationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->can('users.create')) {
            return response()->json([
                'message' => 'You do not have permission to register users.',
            ], 403);
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(12),
                'max:128',
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'employee',
                    'fleet_admin',
                    'management',
                    'system_admin',
                ]),
            ],
        ]);

        $roleName = $data['role'] ?? 'employee';

        // Only the System Administrator can create other
        // System Administrators or privileged staff accounts.
        if (
            $roleName !== 'employee'
            && ! $actor->hasRole('system_admin')
        ) {
            return response()->json([
                'message' => 'Only a System Administrator can assign this role.',
            ], 403);
        }

        $role = Role::findByName($roleName, 'web');

        $user = DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            $user->assignRole($role);

            \App\Support\AuditTrail::write(
                'user.registered',
                $user,
                ['role' => $role->name]
            );

            return $user;
        });

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
            ],
        ], 201);
    }
}
