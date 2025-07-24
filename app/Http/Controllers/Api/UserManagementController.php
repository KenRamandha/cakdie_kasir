<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:pemilik');
    }

    public function index(Request $request)
    {
        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name or username
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->with(['createdBy:id,name', 'updatedBy:id,name'])
                      ->withCount('sales')
                      ->orderBy('created_at', 'desc')
                      ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $users->makeHidden(['password'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users|max:255',
            'email' => 'nullable|email|unique:users|max:255',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:pemilik,pegawai',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => $request->get('is_active', true),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->makeHidden(['password'])->load('createdBy:id,name')
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'success' => true,
            'data' => $user->load([
                'createdBy:id,name',
                'updatedBy:id,name',
                'sales' => function($query) {
                    $query->select('id', 'code', 'total', 'transaction_date', 'cashier_id')
                          ->orderBy('transaction_date', 'desc')
                          ->limit(10);
                }
            ])->makeHidden(['password'])
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'role' => 'required|in:pemilik,pegawai',
            'is_active' => 'boolean',
        ]);

        // Prevent user from changing their own role
        if ($user->id === Auth::id() && $user->role !== $request->role) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own role'
            ], 422);
        }

        $user->update([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => $request->get('is_active', $user->is_active),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->makeHidden(['password'])->load('updatedBy:id,name')
        ]);
    }

    public function destroy(User $user)
    {
        // Prevent user from deleting themselves
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 422);
        }

        // Check if user has sales
        if ($user->sales()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete user that has sales history'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    public function resetPassword(Request $request, User $user)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    public function toggle(User $user)
    {
        // Prevent user from deactivating themselves
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account'
            ], 422);
        }

        $user->update([
            'is_active' => !$user->is_active,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user->makeHidden(['password'])
        ]);
    }

    public function getStats()
    {
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $pemilikCount = User::where('role', 'pemilik')->count();
        $pegawaiCount = User::where('role', 'pegawai')->count();

        // Top cashiers this month
        $topCashiers = User::withCount(['sales' => function($query) {
                                $query->thisMonth();
                            }])
                          ->where('role', 'pegawai')
                          ->where('is_active', true)
                          ->orderBy('sales_count', 'desc')
                          ->limit(5)
                          ->get(['id', 'name', 'username'])
                          ->makeHidden(['password']);

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'pemilik_count' => $pemilikCount,
                'pegawai_count' => $pegawaiCount,
                'top_cashiers' => $topCashiers,
            ]
        ]);
    }
}