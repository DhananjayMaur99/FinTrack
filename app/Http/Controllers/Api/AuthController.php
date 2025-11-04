<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        // Attempt to authenticate the user
        if (! Auth::attempt($request->only('email', 'password'))) {
            // If authentication fails
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401); // 401 Unauthorized
        }

        // If authentication is successful
        $user = User::where('email', $request->email)->firstOrFail();

        // Revoke any old tokens
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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ], 200);
    }
}
