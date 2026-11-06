<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private function details(User $user): array
    {
        $user->load('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'roles' => $user->getRoleNames(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    private function authorizeAction(
        Request $request,
        string $permission,
        ?User $target = null
    ): ?JsonResponse {
        $actor = $request->user();

        if (! $actor->can($permission)) {
            return response()->json([
                'message' => 'Permission denied.',
            ], 403);
        }

        if (
            $target &&
            $target->hasRole('system_admin') &&
            ! $actor->hasRole('system_admin')
        ) {
            return response()->json([
                'message' => 'Cannot manage a System Administrator.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($error = $this->authorizeAction($request, 'users.view')) {
            return $error;
        }

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in(['active', 'suspended', 'inactive']),
            ],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->with('roles');

        if (! empty($data['search'])) {
            $search = addcslashes($data['search'], '%_\\');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['role'])) {
            $query->role($data['role'], 'web');
        }

        $users = $query
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 15);

        $users->getCollection()->transform(
            fn (User $user) => $this->details($user)
        );

        return response()->json($users);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        if ($error = $this->authorizeAction($request, 'users.view', $user)) {
            return $error;
        }

        return response()->json([
            'user' => $this->details($user),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        if ($error = $this->authorizeAction($request, 'users.update', $user)) {
            return $error;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $before = $user->only(['name', 'email']);

        DB::transaction(function () use ($user, $data, $before) {
            $user->update($data);

            \App\Support\AuditTrail::write(
                'user.updated',
                $user,
                [
                    'before' => $before,
                    'after' => $user->only(['name', 'email']),
                ]
            );
        });

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->details($user->fresh()),
        ]);
    }

    public function changeStatus(
        Request $request,
        User $user
    ): JsonResponse {
        if ($error = $this->authorizeAction(
            $request,
            'users.change_status',
            $user
        )) {
            return $error;
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot change your own account status.',
            ], 403);
        }

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(['active', 'suspended', 'inactive']),
            ],
        ]);

        DB::transaction(function () use ($user, $data) {
            $previousStatus = $user->status;

            $user->update(['status' => $data['status']]);

            \App\Support\AuditTrail::write(
                'user.status_changed',
                $user,
                [
                    'before' => $previousStatus,
                    'after' => $data['status'],
                ]
            );

            if ($data['status'] !== 'active') {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'User status updated successfully.',
            'user' => $this->details($user->fresh()),
        ]);
    }

    public function assignRoles(
        Request $request,
        User $user
    ): JsonResponse {
        if (! $request->user()->hasRole('system_admin')) {
            return response()->json([
                'message' => 'Only System Administrators can assign roles.',
            ], 403);
        }

        if ($error = $this->authorizeAction(
            $request,
            'users.assign_roles',
            $user
        )) {
            return $error;
        }

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot change your own roles.',
            ], 403);
        }

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'required',
                'string',
                'distinct',
                Rule::in([
                    'employee',
                    'fleet_admin',
                    'management',
                    'system_admin',
                ]),
            ],
        ]);

        DB::transaction(function () use ($user, $data) {
            $target = User::whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $target->hasRole('system_admin') &&
                ! in_array('system_admin', $data['roles'], true)
            ) {
                $administratorCount = User::role(
                    'system_admin',
                    'web'
                )->count();

                if ($administratorCount <= 1) {
                    abort(422, 'Cannot remove the final System Administrator.');
                }
            }

            $roles = Role::where('guard_name', 'web')
                ->whereIn('name', $data['roles'])
                ->get();

            $previousRoles = $target->getRoleNames()->all();

            $target->syncRoles($roles);

            \App\Support\AuditTrail::write(
                'user.roles_changed',
                $target,
                [
                    'before' => $previousRoles,
                    'after' => $target->getRoleNames()->all(),
                ]
            );
        });

        return response()->json([
            'message' => 'User roles updated successfully.',
            'user' => $this->details($user->fresh()),
        ]);
    }
}
