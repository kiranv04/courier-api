<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DB::table('states')->insert(['id' => 1, 'name' => 'Karnataka', 'gst_code' => '29', 'created_at' => now(), 'updated_at' => now()]);  // explicit id: InnoDB auto-increment survives rollback
        // FK targets for the placeholder id 1 used by branch fixtures
        DB::table('locations')->insert([
            'id' => 1, 'name' => 'Loc', 'short_code' => 'LOC', 'pincode' => '560001', 'state_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function branch(string $name): Branch
    {
        $b = new Branch();
        foreach (DB::getSchemaBuilder()->getColumns('branches') as $col) {
            if (in_array($col['name'], ['id', 'created_at', 'updated_at'])) {
                continue;
            }
            if (! $col['nullable'] && $col['default'] === null) {
                $b->{$col['name']} = match (true) {
                    str_contains($col['type'], 'int'), str_contains($col['type'], 'bool') => 1,
                    str_contains($col['type'], 'decimal'), str_contains($col['type'], 'float') => 1,
                    default => $name . '-' . $col['name'],
                };
            }
        }
        $b->forceFill(['name' => $name, 'code' => strtoupper(substr($name, 0, 3)) . rand(10, 99)]);
        $b->save();
        return $b;
    }

    private function user(string $role, ?Branch $branch = null, ?string $email = null): User
    {
        $u = User::factory()->create([
            'email'                => $email ?? $role . rand(1, 99999) . '@t.test',
            'owner_type'           => $branch ? Branch::class : 'system',
            'owner_id'             => $branch?->id ?? 0,
            'must_change_password' => false,
            'is_active'            => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    private function userPayload(string $role, array $extra = []): array
    {
        return array_merge([
            'name' => 'New User', 'email' => 'n' . rand(1, 99999) . '@t.test', 'role' => $role,
            'password' => 'password123', 'password_confirmation' => 'password123', 'phone' => '9876543210',
        ], $extra);
    }

    public function test_branch_employee_cannot_manage_users(): void
    {
        $a = $this->branch('Alpha');
        $emp = $this->user('branch-employee', $a);

        $this->actingAs($emp)->postJson('/api/users', $this->userPayload('admin'))->assertForbidden();
        $this->actingAs($emp)->getJson('/api/users')->assertForbidden();
    }

    public function test_branch_admin_can_only_create_staff_in_own_branch(): void
    {
        $a = $this->branch('Alpha');
        $b = $this->branch('Bravo');
        $admin = $this->user('branch-admin', $a);

        $this->actingAs($admin)->postJson('/api/users', $this->userPayload('admin'))->assertForbidden();
        $this->actingAs($admin)->postJson('/api/users', $this->userPayload('branch-admin'))->assertForbidden();

        // owner fields in the payload are ignored: the new user is forced into the admin's branch
        $res = $this->actingAs($admin)->postJson('/api/users', $this->userPayload('branch-employee', [
            'owner_type' => Branch::class, 'owner_id' => $b->id,
        ]))->assertCreated();

        $this->assertSame($a->id, (int) User::find($res->json('data.id'))->owner_id);
    }

    public function test_branch_admin_cannot_touch_other_branch_users_or_head_office(): void
    {
        $a = $this->branch('Alpha');
        $b = $this->branch('Bravo');
        $admin = $this->user('branch-admin', $a);
        $otherEmp = $this->user('branch-employee', $b);
        $super = $this->user('super-admin');

        $payload = ['password' => 'newpassword1', 'password_confirmation' => 'newpassword1'];

        $this->actingAs($admin)->postJson("/api/users/{$otherEmp->id}/reset-password", $payload)->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/users/{$otherEmp->id}")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/users/{$super->id}/reset-password", $payload)->assertForbidden();

        $own = $this->user('branch-employee', $a);
        $this->actingAs($admin)->postJson("/api/users/{$own->id}/reset-password", $payload)->assertOk();
    }

    public function test_shipments_and_customers_are_scoped_to_branch(): void
    {
        $a = $this->branch('Alpha');
        $b = $this->branch('Bravo');
        $empB = $this->user('branch-employee', $b);
        $empA = $this->user('branch-employee', $a);

        $customer = new Customer();
        $customer->forceFill([
            'branch_id' => $a->id, 'company_name' => 'Acme', 'customer_code' => 'C1',
            'customer_type' => 'cash', 'type' => 'company', 'created_by' => $empA->id,
        ])->save();
        $shipment = new Shipment();
        $shipment->forceFill(['awb_number' => 'AWB1', 'branch_id' => $a->id, 'status' => 'booked'])->save();

        $this->actingAs($empB)->getJson("/api/shipments/{$shipment->id}")->assertForbidden();
        $this->actingAs($empB)->getJson("/api/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($empB)->getJson('/api/shipments')->assertOk()->assertJsonCount(0, 'data.data');

        $this->actingAs($empA)->getJson("/api/customers/{$customer->id}")->assertOk();
        $this->actingAs($empA)->getJson('/api/shipments')->assertOk()->assertJsonCount(1, 'data.data');
    }

    public function test_must_change_password_blocks_other_endpoints(): void
    {
        $super = $this->user('super-admin');
        $super->update(['must_change_password' => true]);

        $this->actingAs($super)->getJson('/api/me')->assertOk();
        $this->actingAs($super)->getJson('/api/states')->assertForbidden();
    }

    public function test_public_tracking_hides_internal_notes_and_masks_name(): void
    {
        $a = $this->branch('Alpha');
        $s = new Shipment();
        $s->forceFill(['awb_number' => 'AWB9', 'branch_id' => $a->id, 'status' => 'booked', 'consignee_name' => 'Rahul Sharma'])->save();

        $res = $this->getJson('/api/track/AWB9')->assertOk();
        $this->assertSame('R**** S*****', $res->json('data.consignee_name'));
        $this->assertArrayNotHasKey('consignee_pincode', $res->json('data'));
    }
}
