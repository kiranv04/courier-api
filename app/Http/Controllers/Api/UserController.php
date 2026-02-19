<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
       $query = User::query();

        if ($request->has('role')) {
            $query->role($request->query('role'));
        }

        return response()->json($query->with('roles')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role'     => 'required|in:admin,branch-admin,warehouse-admin,branch-employee,branch-delivery,warehouse-employee',
            'password' => 'required|string|min:8|confirmed',
            'must_change_password' => 'sometimes|boolean',
            'owner_type' => 'nullable|required_with:owner_id|string',
            'owner_id'   => 'nullable|required_with:owner_type|integer',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        $user->assignRole($request->role);

        if ($request->filled('owner_type') && $request->filled('owner_id')) {
            $user->owner_type = $request->owner_type;
            $user->owner_id   = $request->owner_id;
            $user->rel_type = $request->rel_type;
            $user->rel_id = $request->rel_id;
            $user->save();
        }

        return response()->json([
            'message' => 'User created successfully!',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $updateFields = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|string|max:255|unique:users,email,' . $user->id,
            'owner_type' => 'nullable|required_with:owner_id|string',
            'owner_id'   => 'nullable|required_with:owner_type|integer'
        ]);

        $user->update($updateFields);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function activate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'message' => 'User activated successfully'
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (!$user->hasAnyRole(['branch-admin', 'branch-employee', 'branch-delivery', 'warehouse-admin', 'warehouse-employee'])) {
            return response()->json(['message' => 'Cannot reset this user type'], 403);
        }

        $newPassword = $request->validate([
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password'
        ]);

        $user->update([
            'password' => Hash::make($newPassword['password']),
        ]);

        return response()->json([
            'message' => 'Password reset successfully',
            'new_password' => $newPassword,
            'user' => $user->load('roles')
        ]);
    }

    public function branchUsers($branchId)
    {
        $users = User::role(['branch-delivery', 'branch-employee'])
            ->where('owner_type', Branch::class)
            ->where('owner_id', $branchId)
            ->with('roles')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }
}
