<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(CreateUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'selected_model' => config('openrouter.default_model', 'anthropic/claude-3.5-sonnet'),
            'confidence_threshold' => config('legal.confidence_threshold', 0.70),
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'selected_model' => $user->selected_model,
                    'confidence_threshold' => (float) $user->confidence_threshold,
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'token' => $token,
            ],
            'meta' => ['message' => 'User registered successfully'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'selected_model' => $user->selected_model,
                    'confidence_threshold' => (float) $user->confidence_threshold,
                ],
                'token' => $token,
            ],
            'meta' => [
                'message' => 'Login successful',
                'expires_in_days' => 7,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'meta' => ['message' => 'Logged out successfully'],
        ]);
    }
}
