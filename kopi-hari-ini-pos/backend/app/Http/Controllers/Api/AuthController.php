<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi admin.'], 403);
        }

        // Record login time
        $user->update(['last_login_at' => now()]);

        // Create Sanctum token
        $token = $user->createToken('pos-token')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user'  => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'username' => $user->username,
                    'role'     => $user->role,
                ],
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'data' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'username'      => $user->username,
                'role'          => $user->role,
                'last_login_at' => $user->last_login_at,
            ],
        ]);
    }
}
