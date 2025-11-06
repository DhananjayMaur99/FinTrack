<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Register a new user and return a token
    public function register(RegisterUserRequest $request)
    {
        // Request is already validated by RegisterUserRequest

        // create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // create a token for new user

        $token = $user->createToken('api-token');

        // Return User and Token

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
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
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401); // 401 Unauthorized
        }

        // If authentication is successful, revoke any old tokens
        $user->tokens()->delete();

        // Create a new token
        $token = $user->createToken('api-token');

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
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

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Revoke all tokens for this user (Sanctum)
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        // Soft delete the user. This will keep records for historical integrity.
        // If a permanent delete is desired, a separate admin/confirm flow should
        // call forceDelete().
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ], 200);
    }
}
