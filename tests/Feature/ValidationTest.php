<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shipment;
use App\Models\ShipmentAssignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DB::table('states')->insert(['id' => 1, 'name' => 'Karnataka', 'gst_code' => '29', 'created_at' => now(), 'updated_at' => now()]);  // explicit id: InnoDB auto-increment survives rollback
        // FK target for the placeholder location_id 1 used by branch fixtures
        DB::table('locations')->insert([
            'id' => 1, 'name' => 'Loc1', 'short_code' => 'LC1', 'pincode' => '560001', 'state_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function actor(string $role, int $ownerId = 0, string $ownerType = 'system'): User
    {
        $u = User::factory()->create([
            'owner_type' => $ownerType, 'owner_id' => $ownerId,
            'must_change_password' => false, 'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    private function location(): int
    {
        return DB::table('locations')->insertGetId([
            'name' => 'Loc', 'short_code' => 'L'.random_int(100, 999), 'pincode' => (string) random_int(500000, 899999), 'state_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_api_errors_are_json_even_without_accept_header(): void
    {
        // no Accept header: used to be a 500 (no "login" route) instead of a 401
        $this->get('/api/shipments')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
        $this->get('/api/does-not-exist')->assertStatus(404)->assertJson(['message' => 'Resource not found.']);
    }

    public function test_users_role_filter_is_validated(): void
    {
        $super = $this->actor('super-admin');
        $this->actingAs($super)->getJson('/api/users?role=bogus')->assertStatus(422)->assertJsonValidationErrors('role');
        $this->actingAs($super)->getJson('/api/users?role=branch-admin')->assertOk();
    }

    public function test_head_office_user_creation_checks_owner_and_role_family(): void
    {
        $super = $this->actor('super-admin');
        $base = ['name' => 'N', 'email' => 'a@t.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'phone' => '9876543210'];

        // branch role without owner -> 422 (used to be a DB 500)
        $this->actingAs($super)->postJson('/api/users', $base + ['role' => 'branch-admin'])->assertStatus(422);
        // owner that does not exist
        $this->actingAs($super)->postJson('/api/users', $base + ['role' => 'branch-admin', 'owner_type' => Branch::class, 'owner_id' => 999])->assertStatus(422);
        // head-office admin needs no owner
        $this->actingAs($super)->postJson('/api/users', $base + ['role' => 'admin'])->assertCreated();
    }

    public function test_branch_can_be_created_without_optional_fields(): void
    {
        $super = $this->actor('super-admin');
        $res = $this->actingAs($super)->postJson('/api/branches', [
            'name' => 'Alpha', 'code' => 'ALP', 'locationId' => $this->location(), 'addressLine1' => 'x',
            'phone' => '9876543210', 'email' => 'b@t.test', 'yieldRatioDoor' => 10, 'yieldRatioWarehouse' => 10, 'state' => 1,
        ]);
        $res->assertCreated();
    }

    public function test_delivery_fail_and_cheque_payment_do_not_require_optional_fields(): void
    {
        $branch = new Branch();
        $branch->forceFill(['name' => 'A', 'code' => 'A1', 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => 'a@a.test', 'yield_ratio_door' => 1, 'yield_ratio_warehouse' => 1, 'state_id' => 1])->save();

        $agent = $this->actor('branch-delivery', $branch->id, Branch::class);
        $admin = $this->actor('branch-admin', $branch->id, Branch::class);

        $shipment = new Shipment();
        $shipment->forceFill(['awb_number' => 'AWB5', 'branch_id' => $branch->id, 'status' => 'out_for_delivery'])->save();
        ShipmentAssignment::create([
            'shipment_id' => $shipment->id, 'assigned_to' => $agent->id, 'assigned_by' => $admin->id,
            'assignment_type' => 'delivery', 'status' => 'pending',
        ]);

        $this->actingAs($agent)->postJson("/api/delivery/shipments/{$shipment->id}/fail", ['reason' => 'damaged'])->assertOk();

        $this->actingAs($admin)->postJson("/api/shipments/{$shipment->id}/payment", ['payment_type' => 'cheque', 'amount' => 100])->assertCreated();
    }

    public function test_customer_create_validates_shipping_address_and_does_not_orphan_rows(): void
    {
        $super = $this->actor('super-admin');
        $branch = new Branch();
        $branch->forceFill(['name' => 'A', 'code' => 'A1', 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => 'a@a.test', 'yield_ratio_door' => 1, 'yield_ratio_warehouse' => 1, 'state_id' => 1])->save();

        $base = [
            'branch_id' => $branch->id, 'company_name' => 'Acme', 'customer_type' => 'cash', 'type' => 'company',
            'billing_name' => 'B', 'billing_address_line1' => 'x', 'billing_city' => 'c', 'billing_pincode' => '560001',
            'billing_phone' => '9876543210', 'billing_state_id' => 1,
        ];

        // same_address = "0" (multipart string) with no shipping fields -> 422, nothing persisted
        $this->actingAs($super)->postJson('/api/customers', $base + ['same_address' => '0'])->assertStatus(422);
        $this->assertSame(0, \App\Models\Customer::count());

        // head office must pick a branch
        $noBranch = $base;
        unset($noBranch['branch_id']);
        $this->actingAs($super)->postJson('/api/customers', $noBranch + ['same_address' => '1'])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->actingAs($super)->postJson('/api/customers', $base + ['same_address' => '1'])->assertCreated();
        $this->assertSame(1, \App\Models\Customer::count());
    }

    public function test_customer_update_works_via_post_with_method_spoofing(): void
    {
        $super = $this->actor('super-admin');
        $branch = new Branch();
        $branch->forceFill(['name' => 'A', 'code' => 'A1', 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => 'a@a.test', 'yield_ratio_door' => 1, 'yield_ratio_warehouse' => 1, 'state_id' => 1])->save();
        $customer = new \App\Models\Customer();
        $customer->forceFill(['branch_id' => $branch->id, 'company_name' => 'Old', 'customer_code' => 'C9',
            'customer_type' => 'cash', 'type' => 'company', 'created_by' => $super->id])->save();

        // This is how the UI sends multipart edits: POST + _method=PUT
        $this->actingAs($super)->post("/api/customers/{$customer->id}", ['_method' => 'PUT', 'company_name' => 'New'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('New', $customer->fresh()->company_name);
    }
}
