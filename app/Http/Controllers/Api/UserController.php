<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    private const HEAD_OFFICE_ROLES = ['super-admin', 'admin'];

    /** Roles a scoped admin may manage, keyed by the admin's own role. */
    private const SCOPED_MANAGEABLE = [
        'branch-admin'    => ['branch-employee', 'branch-delivery'],
        'warehouse-admin' => ['warehouse-employee'],
    ];

    private function actorIsHeadOffice(User $actor): bool
    {
        return $actor->hasAnyRole(self::HEAD_OFFICE_ROLES);
    }

    /** Roles the acting user is allowed to create / manage. */
    private function manageableRoles(User $actor): array
    {
        if ($actor->hasRole('super-admin')) {
            return ['admin', 'branch-admin', 'warehouse-admin', 'branch-employee', 'branch-delivery', 'warehouse-employee'];
        }
        if ($actor->hasRole('admin')) {
            return ['branch-admin', 'warehouse-admin', 'branch-employee', 'branch-delivery', 'warehouse-employee'];
        }
        foreach (self::SCOPED_MANAGEABLE as $role => $manageable) {
            if ($actor->hasRole($role)) {
                return $manageable;
            }
        }
        return [];
    }

    private function ownerClassFor(User $actor): ?string
    {
        return $actor->hasRole('branch-admin') ? Branch::class
            : ($actor->hasRole('warehouse-admin') ? Warehouse::class : null);
    }

    /** Abort with 403 unless $actor may manage $target. */
    private function authorizeTarget(User $actor, User $target): void
    {
        $ok = $target->id !== $actor->id
            && $target->hasAnyRole($this->manageableRoles($actor))
            && ($this->actorIsHeadOffice($actor)
                || ($target->owner_type === $this->ownerClassFor($actor)
                    && (int) $target->owner_id === (int) $actor->owner_id));

        abort_unless($ok, 403, 'You are not allowed to manage this user.');
    }

    private const ROLE_OWNER_TYPE = [
        'branch-admin'       => Branch::class,
        'branch-employee'    => Branch::class,
        'branch-delivery'    => Branch::class,
        'warehouse-admin'    => Warehouse::class,
        'warehouse-employee' => Warehouse::class,
    ];

    /** Rule: owner_id must exist in the table implied by owner_type. */
    private function ownerExistsRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            $type = request()->input('owner_type');
            if (! $value || ! $type) {
                return;
            }
            if (! $type::query()->whereKey($value)->exists()) {
                $fail('The selected owner does not exist.');
            }
        };
    }

    private function forgetSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
        ]);

        $actor = $request->user();
        $query = User::query();

        if (! $this->actorIsHeadOffice($actor)) {
            $query->role($this->manageableRoles($actor))
                ->where('owner_type', $this->ownerClassFor($actor))
                ->where('owner_id', $actor->owner_id);
        }

        if ($request->filled('role')) {
            $query->role($request->query('role'));
        }

        return response()->json($query->with('roles')->paginate(20));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $needsOwner = fn () => $request->input('role') !== 'admin' && $this->actorIsHeadOffice($request->user());

        $data = $request->validate([
            'name' => 'required|string|max:125',
            'email' => 'required|string|email|max:125|unique:users,email',
            'role'     => 'required|in:admin,branch-admin,warehouse-admin,branch-employee,branch-delivery,warehouse-employee',
            'password' => 'required|string|min:8|confirmed',
            'must_change_password' => 'sometimes|boolean',
            'owner_type' => [Rule::requiredIf($needsOwner), 'nullable', 'required_with:owner_id', 'in:App\Models\Branch,App\Models\Warehouse'],
            'owner_id'   => [Rule::requiredIf($needsOwner), 'nullable', 'required_with:owner_type', 'integer', 'min:1', $this->ownerExistsRule()],
            'rel_type'   => 'nullable|required_with:rel_id|in:App\Models\Branch,App\Models\Warehouse',
            'rel_id'     => 'nullable|required_with:rel_type|integer|min:1|max:65535',
            'phone' => 'required|regex:/^[6-9]\d{9}$/',
            'yield_ratio_door' => 'nullable|numeric|min:0|max:100',
            'yield_ratio_warehouse' => 'nullable|numeric|min:0|max:100',
        ]);

        $actor = $request->user();

        if (! in_array($data['role'], $this->manageableRoles($actor), true)) {
            abort(403, 'You are not allowed to create users with this role.');
        }

        // Scoped admins can only create users inside their own branch / warehouse.
        if (! $this->actorIsHeadOffice($actor)) {
            $data['owner_type'] = $this->ownerClassFor($actor);
            $data['owner_id']   = $actor->owner_id;
        }

        // A branch role must belong to a branch, a warehouse role to a warehouse.
        $expected = self::ROLE_OWNER_TYPE[$data['role']] ?? null;
        if ($expected && ($data['owner_type'] ?? null) !== $expected) {
            return response()->json([
                'message' => 'The owner does not match the role.',
                'errors'  => ['owner_type' => ['A ' . $data['role'] . ' must be assigned to a ' . class_basename($expected) . '.']],
            ], 422);
        }

        // Head-office admins have no branch/warehouse (owner columns are NOT NULL)
        $data['owner_type'] = $data['owner_type'] ?? 'system';
        $data['owner_id']   = $data['owner_id'] ?? 0;

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        $user->assignRole($request->role);

        $user->rel_type = $data['rel_type'] ?? null;
        $user->rel_id   = $data['rel_id'] ?? null;
        $user->save();

        return response()->json([
            'message' => 'User created successfully!',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user)
    {
        if ($request->user()->id !== $user->id) {
            $this->authorizeTarget($request->user(), $user);
        }

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $user);

        $updateFields = $request->validate([
            'name'     => 'required|string|max:125',
            'email'    => 'required|email|string|max:125|unique:users,email,' . $user->id,
            'owner_type' => 'nullable|required_with:owner_id|in:App\Models\Branch,App\Models\Warehouse',
            'owner_id'   => ['nullable', 'required_with:owner_type', 'integer', 'min:1', $this->ownerExistsRule()],
            'phone' => 'required|regex:/^[6-9]\d{9}$/',
            'yield_ratio_door' => 'nullable|numeric|min:0|max:100',
            'yield_ratio_warehouse' => 'nullable|numeric|min:0|max:100',
        ]);

        // owner columns are NOT NULL: only change them when a complete pair was sent, and keep the role family
        $expected = null;
        foreach (self::ROLE_OWNER_TYPE as $role => $type) {
            if ($user->hasRole($role)) {
                $expected = $type;
            }
        }
        if (empty($updateFields['owner_type']) || empty($updateFields['owner_id'])
            || ($expected && $updateFields['owner_type'] !== $expected)) {
            unset($updateFields['owner_type'], $updateFields['owner_id']);
        }

        // Only head office may move a user to another branch / warehouse.
        if (! $this->actorIsHeadOffice($actor)) {
            unset($updateFields['owner_type'], $updateFields['owner_id']);
        }

        $user->update($updateFields);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorizeTarget($request->user(), $user);

        $user->update(['is_active' => false]);
        $this->forgetSessions($user);

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    public function activate(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->authorizeTarget($request->user(), $user);
        $user->update(['is_active' => true]);

        return response()->json([
            'message' => 'User activated successfully'
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorizeTarget($request->user(), $user);

        if (!$user->hasAnyRole(['branch-admin', 'branch-employee', 'branch-delivery', 'warehouse-admin', 'warehouse-employee'])) {
            return response()->json(['message' => 'Cannot reset this user type'], 403);
        }

        $newPassword = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($newPassword['password']),
        ]);
        $this->forgetSessions($user);

        return response()->json([
            'message' => 'Password reset successfully',
            'user' => $user->load('roles')
        ]);
    }

    public function branchUsers(Request $request, $branchId)
    {
        $actor = $request->user();
        if (! $this->actorIsHeadOffice($actor)) {
            abort_unless(
                $actor->hasAnyRole(['branch-admin', 'branch-employee'])
                    && $actor->owner_type === Branch::class
                    && (int) $actor->owner_id === (int) $branchId,
                403
            );
        }

        $users = User::role(['branch-delivery', 'branch-employee'])
            ->where('owner_type', Branch::class)
            ->where('owner_id', $branchId)
            ->with('roles')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }

    public function warehouseUsers(Request $request, $warehouseId)
    {
        $actor = $request->user();
        if (! $this->actorIsHeadOffice($actor)) {
            abort_unless(
                $actor->hasRole('warehouse-admin')
                    && $actor->owner_type === Warehouse::class
                    && (int) $actor->owner_id === (int) $warehouseId,
                403
            );
        }

        $users = User::role(['warehouse-employee'])
            ->where('owner_type', Warehouse::class)
            ->where('owner_id', $warehouseId)
            ->with('roles')
            ->get();

        return response()->json([
            'data' => $users
        ]);
    }
}
