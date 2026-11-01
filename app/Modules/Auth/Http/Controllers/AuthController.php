<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Modules\Auth\Http\Requests\LoginUserRequest;
use App\Modules\Auth\Http\Requests\RegisterUserRequest;
use App\Modules\Auth\Http\Requests\UserUpdateRequest;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\TransientToken;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Issue a Sanctum token with configured TTL.
     */
    protected function issueToken(User $user): array
    {
        $ttlMinutes = config('token.ttl_minutes', 60);
        $expiresAt = now()->addMinutes($ttlMinutes);
        $token = $user->createToken('api-token', ['*'], $expiresAt);
        return [
            'plain' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in' => $ttlMinutes * 60, // seconds
        ];
    }

    /**
     * For same-origin requests from the Blade+Vue web app (made stateful by
     * EnsureFrontendRequestsAreStateful), also log the user into the session
     * guard so subsequent /api/* calls authenticate via cookie, not a token.
     * Bearer-token API clients never have a session, so this is a no-op for them.
     */
    protected function startSessionForStatefulRequest(Request $request, User $user): void
    {
        if (! $request->hasSession()) {
            return;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
    }

    // Register a new user and return a token
    public function register(RegisterUserRequest $request)
    {
        // Request is already validated by RegisterUserRequest

        // create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'timezone' => $request->timezone, // Optional timezone during registration
        ]);

        $this->startSessionForStatefulRequest($request, $user);

        // create a token for new user - 1 hour expiry
        $issued = $this->issueToken($user);
        return response()->json([
            'user' => $user,
            'token' => $issued['plain'],
            'expires_at' => $issued['expires_at'],
            'expires_in' => $issued['expires_in'],
        ], 201);
    }

    /**
     * Login an existing User and return a token
     */
    public function login(LoginUserRequest $request)
    {
        // The request is already validated by LoginUserRequest

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password matches
        if (! $user || ! password_verify($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Note: we deliberately do NOT revoke the user's other tokens here, so a login on a
        // second device (e.g. the mobile PWA) doesn't silently log the user out elsewhere.
        // Use /logout-other-devices for an explicit "sign out everywhere else" action.

        $this->startSessionForStatefulRequest($request, $user);

        // Create a new token - 1 hour expiry
        $issued = $this->issueToken($user);
        return response()->json([
            'user' => $user,
            'token' => $issued['plain'],
            'expires_at' => $issued['expires_at'],
            'expires_in' => $issued['expires_in'],
        ]);
    }

    /**
     * Log out the current user (revoke their token).
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $token = $request->user()->currentAccessToken();

        // Check if it's an actual token (not a TransientToken from testing)
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Revoke every token for the current user except the one used for this request.
     */
    public function logoutOtherDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();

        $user->tokens()
            ->when($currentToken && method_exists($currentToken, 'getKey'), function ($query) use ($currentToken) {
                $query->where('id', '!=', $currentToken->getKey());
            })
            ->delete();

        return response()->json([
            'message' => 'Logged out of other devices',
        ], 200);
    }

    /**
     * Soft-delete the authenticated user and revoke their tokens.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        // Revoke all tokens for this user (Sanctum)
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // Soft delete the user. This will keep records for historical integrity.

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ], 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        if ($current && !($current instanceof TransientToken)) {
            $current->delete();
        }

        $issued = $this->issueToken($request->user());
        return response()->json([
            'token' => $issued['plain'],
            'expires_at' => $issued['expires_at'],
            'expires_in' => $issued['expires_in'],
        ]);
    }

    /**
     * Update the authenticated user's profile (name, email, timezone, password).
     */
    public function updateProfile(UserUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return response()->json([
            'user' => $user,
        ]);
    }
}
