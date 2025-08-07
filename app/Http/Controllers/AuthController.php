<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required',
                'password' => 'required',
            ]);

            $user = User::where('username', $request->username)
                ->where('is_active', true)
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Username tidak ditemukan atau akun tidak aktif',
                    'errors' => [
                        'username' => ['Username tidak ditemukan atau akun tidak aktif']
                    ]
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Password salah',
                    'errors' => [
                        'password' => ['Password salah']
                    ]
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->makeHidden(['password'])
            ]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal melakukan login: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json(['message' => 'Berhasil logout']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal logout: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
