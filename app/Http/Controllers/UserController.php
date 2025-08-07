<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Traits\ChecksPermissions;

class UserController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $users = User::select('user_id', 'name', 'username', 'email', 'role', 'is_active', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data pengguna: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
                'role' => 'required|in:pemilik,pegawai',
            ]);

            if ($validator->fails()) {
                $firstError = collect($validator->errors())->first()[0];
                return response()->json([
                    'message' => 'Validasi gagal: ' . $firstError,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::create([
                'user_id' => 'USR-' . Str::random(8),
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'is_active' => true,
                'created_by' => $request->user()->user_id,
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->makeHidden(['password'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat pengguna: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function show(Request $request, $user_id)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $user = User::select('user_id', 'name', 'username', 'email', 'role', 'is_active', 'created_at')
                ->where('user_id', $user_id)
                ->firstOrFail();

            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pengguna tidak ditemukan',
                'errors' => [
                    'not_found' => ['Pengguna yang diminta tidak dapat ditemukan']
                ]
            ], 404);
        }
    }

    public function update(Request $request, $user_id)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $user = User::where('user_id', $user_id)->firstOrFail();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'username' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)
                ],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id)
                ],
                'password' => 'nullable|string|min:8',
                'role' => 'required|in:pemilik,pegawai',
            ]);

            if ($validator->fails()) {
                $firstError = collect($validator->errors())->first()[0];
                return response()->json([
                    'message' => 'Validasi gagal: ' . $firstError,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'role' => $request->role,
                'updated_by' => $request->user()->user_id,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user->fresh()->makeHidden(['password'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui pengguna: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function destroy(Request $request, $user_id)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $user = User::where('user_id', $user_id)->firstOrFail();

            if ($user->user_id === $request->user()->user_id) {
                return response()->json([
                    'message' => 'Tidak dapat menghapus akun sendiri',
                    'errors' => [
                        'validation' => ['Tidak dapat menghapus akun sendiri']
                    ]
                ], 422);
            }

            $user->update([
                'is_active' => false,
                'updated_by' => $request->user()->user_id
            ]);

            return response()->json(['message' => 'User deactivated successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menonaktifkan pengguna: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function toggleStatus(Request $request, $user_id)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $user = User::where('user_id', $user_id)->firstOrFail();

            if ($user->user_id === $request->user()->user_id) {
                return response()->json([
                    'message' => 'Tidak dapat menonaktifkan akun sendiri',
                    'errors' => [
                        'validation' => ['Tidak dapat menonaktifkan akun sendiri']
                    ]
                ], 422);
            }

            $user->update([
                'is_active' => !$user->is_active,
                'updated_by' => $request->user()->user_id,
            ]);

            $status = $user->is_active ? 'activated' : 'deactivated';

            return response()->json([
                'message' => "User {$status} successfully",
                'user' => $user->fresh()->makeHidden(['password'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengubah status pengguna: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
