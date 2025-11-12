<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\User;
use Illuminate\Http\Request;
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

        $user->tokens()->get()->each->delete();

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

        return response()->json([
            'message' => 'Logged out successfully',
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
            'user' => $user->fresh(),
        ]);
    }
}
