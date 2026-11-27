<?php

namespace Tests\Concerns;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Shared fixtures for feature tests. Call seedReferenceData() from setUp().
 * Ids are explicit because InnoDB auto-increment survives RefreshDatabase rollbacks.
 */
trait BuildsCourierFixtures
{
    protected function seedReferenceData(): void
    {
        $this->seed(RoleSeeder::class);
        DB::table('states')->insert([
            ['id' => 1, 'name' => 'Karnataka', 'gst_code' => '29', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tamil Nadu', 'gst_code' => '33', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('locations')->insert([
            'id' => 1, 'name' => 'Loc', 'short_code' => 'LOC', 'pincode' => '560001', 'state_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function makeBranch(string $code): Branch
    {
        $b = new Branch();
        $b->forceFill(['name' => $code, 'code' => $code, 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => strtolower($code) . '@t.test', 'yield_ratio_door' => 1, 'yield_ratio_warehouse' => 1, 'state_id' => 1])->save();
        return $b;
    }

    protected function makeWarehouse(string $code): Warehouse
    {
        $w = new Warehouse();
        $w->forceFill(['name' => $code, 'code' => $code, 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => strtolower($code) . '@t.test', 'state_id' => 1])->save();
        return $w;
    }

    protected function makeUser(string $role, ?object $owner = null): User
    {
        $u = User::factory()->create([
            'owner_type' => $owner ? get_class($owner) : 'system',
            'owner_id' => $owner?->id ?? 0,
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    protected function makeCustomer(Branch $branch, string $type = 'corporate', string $code = 'C1'): Customer
    {
        $c = new Customer();
        $c->forceFill(['branch_id' => $branch->id, 'company_name' => 'Acme ' . $code, 'customer_code' => $code,
            'customer_type' => $type, 'type' => 'company', 'created_by' => User::factory()->create(['owner_type' => 'system', 'owner_id' => 0])->id])->save();
        return $c;
    }

    protected function makeShipment(Branch $branch, string $status, array $extra = []): Shipment
    {
        $s = new Shipment();
        $s->forceFill($extra + ['branch_id' => $branch->id, 'status' => $status, 'booked_at' => now()])->save();
        return $s;
    }
}
