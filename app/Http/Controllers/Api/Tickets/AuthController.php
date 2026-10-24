<?php

namespace App\Http\Controllers\Api\Tickets;

use App\Exceptions\TicketsApiException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * TicketTrail shares the users table with ShopSphere, so one account works
 * in both apps. The API accepts any 6+ character password; the register
 * page enforces a stronger policy (a deliberate UI-vs-API gap to test).
 */
class AuthController extends TicketsController
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
            throw new TicketsApiException('Email already registered');
        }

        $user = User::create(['email' => $data['email'], 'password' => $data['password']]);

        return $this->success($this->signIn($user), 201);
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

        // Distinct "not found" and "invalid credentials" errors are deliberate:
        // learners practise asserting on each case.
        $user = User::where('email', $data['email'])->first()
            ?? throw TicketsApiException::notFound('User not found');

        if (! Hash::check($data['password'], $user->password)) {
            throw new TicketsApiException('Invalid credentials');
        }

        return $this->success($this->signIn($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(['user' => ['userId' => $user->id, 'email' => $user->email]]);
    }

    /**
     * Record the login and issue a new API token.
     *
     * @return array{token: string, user: array{id: int, email: string}}
     */
    private function signIn(User $user): array
    {
        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $user->createToken('tickets')->plainTextToken,
            'user' => ['id' => $user->id, 'email' => $user->email],
        ];
    }
}
