<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active. Contact the administrator.',
            ], 403);
        }

        $token = $user->createToken(
            'kfms-api',
            ['*'],
            now()->addHours(12)
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => 43200,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Do not reveal whether an account exists.
        Password::sendResetLink([
            'email' => $data['email'],
        ]);

        return response()->json([
            'message' => 'If the email belongs to an account, a password reset link will be sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:12',
                'max:128',
            ],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                if ($user->status !== 'active') {
                    throw ValidationException::withMessages([
                        'email' => ['Password reset is not available for this account.'],
                    ]);
                }

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                \App\Support\AuditTrail::write(
                    'user.password_reset',
                    $user
                );

                // Invalidate existing API tokens.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'The password reset token is invalid or expired.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully. Please log in again.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:12',
                'max:128',
            ],
        ]);

        if (! Hash::check(
            $data['current_password'],
            $user->password
        )) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'The current password is incorrect.',
                ],
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [
                    'The new password must differ from the current password.',
                ],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        \App\Support\AuditTrail::write(
            'user.password_changed',
            $user
        );

        // Revoke other API tokens after a password change.
        // Keep the current token so the user can finish securely.
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $user->tokens()
                ->where('id', '!=', $currentToken->id)
                ->delete();
        } else {
            // For session-based authentication, revoke API tokens.
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        $currentToken = $user->currentAccessToken();

        $currentId = $currentToken instanceof
            \Laravel\Sanctum\PersonalAccessToken
                ? $currentToken->id
                : null;

        $tokens = $user->tokens()
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();

        $sessions = $tokens->map(function ($token) use ($currentId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
                'is_current' => $token->id === $currentId,
            ];
        });

        return response()->json([
            'sessions' => $sessions,
            'total' => $sessions->count(),
        ]);
    }

    public function revokeSession(
        Request $request,
        int $id
    ): JsonResponse {
        $user = $request->user();

        // The relationship ensures users cannot revoke
        // tokens belonging to another account.
        $token = $user->tokens()
            ->whereKey($id)
            ->first();

        if (! $token) {
            return response()->json([
                'message' => 'Session not found.',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Session revoked successfully.',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
            ],
        ]);

        if (empty($data)) {
            return response()->json([
                'message' => 'No profile changes provided.',
            ], 422);
        }

        if (
            array_key_exists('email', $data)
            && $data['email'] !== $user->email
        ) {
            if (! Hash::check(
                (string) $request->input('current_password', ''),
                $user->password
            )) {
                return response()->json([
                    'message' => 'Current password is required to change email.',
                ], 422);
            }

            $data['email_verified_at'] = null;
        }

        $before = $user->only(['name', 'email']);

        \Illuminate\Support\Facades\DB::transaction(
            function () use ($user, $data, $before) {
                $user->forceFill($data)->save();

                \App\Support\AuditTrail::write(
                    'user.profile_updated',
                    $user,
                    [
                        'before' => $before,
                        'after' => $user->only(['name', 'email']),
                    ]
                );
            }
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()
                    ->pluck('name'),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }
}
