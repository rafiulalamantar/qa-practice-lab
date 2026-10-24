<?php

namespace App\Http\Controllers\Api\Ecom;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const PASSWORD_MISMATCH_MESSAGE = [
        'confirmPassword.same' => 'Password and Confirm Password must match with each other.',
    ];

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'max:60'],
            'lastName' => ['required', 'string', 'max:60'],
            'userEmail' => ['required', 'email', 'max:255'],
            'userMobile' => ['required', 'digits:10'],
            'occupation' => ['nullable', 'in:Doctor,Student,Engineer,Scientist'],
            'gender' => ['nullable', 'in:Male,Female'],
            ...$this->newPasswordRules(),
        ], self::PASSWORD_MISMATCH_MESSAGE);

        if (User::where('email', $data['userEmail'])->exists()) {
            return response()->json(['message' => 'User already exists with this Email Id!'], 400);
        }

        User::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'email' => $data['userEmail'],
            'mobile' => $data['userMobile'],
            'occupation' => $data['occupation'] ?? null,
            'gender' => $data['gender'] ?? null,
            'password' => $data['userPassword'],
        ]);

        return response()->json(['message' => 'Registered Successfully'], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userEmail' => ['required', 'email'],
            'userPassword' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['userEmail'])->first();

        if (! $user || ! Hash::check($data['userPassword'], $user->password)) {
            return response()->json(['message' => 'Incorrect email or password.'], 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken('client')->plainTextToken,
            'userId' => (string) $user->id,
            'message' => 'Login Successfully',
        ]);
    }

    /**
     * Practice-site password reset: the registered mobile number acts as the
     * second factor, since the site sends no email.
     */
    public function passwordNew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'userEmail' => ['required', 'email'],
            'userMobile' => ['required', 'digits:10'],
            ...$this->newPasswordRules(),
        ], self::PASSWORD_MISMATCH_MESSAGE);

        $user = User::where('email', $data['userEmail'])->where('mobile', $data['userMobile'])->first();

        if (! $user) {
            return response()->json(['message' => 'No account matches this email and mobile number.'], 404);
        }

        if ($user->is_demo) {
            return response()->json(['message' => 'The shared demo account password cannot be changed.'], 403);
        }

        $user->update(['password' => $data['userPassword']]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password Changed Successfully']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout Successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'userId' => (string) $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'userEmail' => $user->email,
        ]);
    }

    /**
     * Shared by register and password reset: 8+ characters with upper and
     * lower case, a number and a symbol, typed twice.
     *
     * @return array<string, list<mixed>>
     */
    private function newPasswordRules(): array
    {
        return [
            'userPassword' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
            'confirmPassword' => ['required', 'same:userPassword'],
        ];
    }
}
