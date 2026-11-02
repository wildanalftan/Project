<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/users
     */
    public function index(): JsonResponse
    {
        $users = User::orderBy('name')->get()->map(fn($u) => [
            'id'            => $u->id,
            'name'          => $u->name,
            'username'      => $u->username,
            'role'          => $u->role,
            'is_active'     => $u->is_active,
            'last_login_at' => $u->last_login_at,
            'created_at'    => $u->created_at,
        ]);

        $countByRole = User::selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'data' => $users,
            'meta' => ['count_by_role' => $countByRole],
        ]);
    }

    /**
     * POST /api/users
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users|regex:/^[a-zA-Z0-9._]+$/',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['admin', 'kasir', 'manajer'])],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($data['password'], ['rounds' => 12]),
            'role'     => $data['role'],
        ]);

        return response()->json(['data' => $user], 201);
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'role'      => ['sometimes', Rule::in(['admin', 'kasir', 'manajer'])],
            'is_active' => 'sometimes|boolean',
            'password'  => 'sometimes|nullable|string|min:8',
        ]);

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password'], ['rounds' => 12]);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json(['data' => $user]);
    }
}
