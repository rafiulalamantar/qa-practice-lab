<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * TicketTrail shares the users table with ShopSphere, so one account works
 * in both apps. The API accepts any 6+ character password; the register
 * page enforces a stronger policy (a deliberate UI-vs-API gap to test).
 */
class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
        ], [
            'email.required' => 'A valid email is required',
            'email.email' => 'A valid email is required',
            'password.min' => 'Password must be at least 6 characters',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['success' => false, 'error' => 'Email already registered'], 400);
        }

        $user = User::create(['email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenResponse($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'A valid email is required',
            'email.email' => 'A valid email is required',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['success' => false, 'error' => 'Invalid credentials'], 400);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json($this->tokenResponse($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['success' => true, 'user' => ['userId' => $user->id, 'email' => $user->email]]);
    }

    private function tokenResponse(User $user): array
    {
        return [
            'success' => true,
            'token' => $user->createToken('tickets')->plainTextToken,
            'user' => ['id' => $user->id, 'email' => $user->email],
        ];
    }
}
