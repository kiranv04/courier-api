<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShipmentStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        DB::table('states')->insert(['id' => 1, 'name' => 'Karnataka', 'gst_code' => '29', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('locations')->insert([
            'id' => 1, 'name' => 'Loc', 'short_code' => 'LOC', 'pincode' => '560001', 'state_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function branch(string $code): Branch
    {
        $b = new Branch();
        $b->forceFill(['name' => $code, 'code' => $code, 'location_id' => 1, 'address_line_1' => 'x', 'phone' => '9876543210',
            'email' => strtolower($code) . '@t.test', 'yield_ratio_door' => 1, 'yield_ratio_warehouse' => 1, 'state_id' => 1])->save();
        return $b;
    }

    private function user(string $role, Branch $branch): User
    {
        $u = User::factory()->create([
            'owner_type' => Branch::class, 'owner_id' => $branch->id,
            'must_change_password' => false, 'is_active' => true,
        ]);
        $u->assignRole($role);
        return $u;
    }

    private function shipment(Branch $branch, string $status, ?string $awb = null): Shipment
    {
        $s = new Shipment();
        $s->forceFill(['awb_number' => $awb, 'branch_id' => $branch->id, 'status' => $status])->save();
        return $s;
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $branch = $this->branch('AAA');
        $admin = $this->user('branch-admin', $branch);
        $shipment = $this->shipment($branch, 'booked');

        $this->actingAs($admin)->patchJson("/api/shipments/{$shipment->id}/statusUpdate", ['status' => 'delivered'])
            ->assertStatus(422);
        $this->assertSame('booked', $shipment->fresh()->status);
    }

    public function test_cancelled_and_delivered_are_terminal(): void
    {
        $branch = $this->branch('AAB');
        $admin = $this->user('branch-admin', $branch);
        $cancelled = $this->shipment($branch, 'cancelled');

        $this->actingAs($admin)->patchJson("/api/shipments/{$cancelled->id}/statusUpdate", ['status' => 'picked_up'])
            ->assertStatus(422);
        $this->actingAs($admin)->getJson("/api/shipments/{$cancelled->id}/transitions")
            ->assertOk()->assertJson(['transitions' => []]);
    }

    public function test_role_restriction_blocks_transition(): void
    {
        $branch = $this->branch('AAC');
        $delivery = $this->user('branch-delivery', $branch);
        $shipment = $this->shipment($branch, 'booked');

        // booked -> cancelled is legal, but a delivery agent may not cancel
        $this->actingAs($delivery)->patchJson("/api/shipments/{$shipment->id}/statusUpdate", ['status' => 'cancelled'])
            ->assertForbidden();
        $this->assertSame('booked', $shipment->fresh()->status);
    }

    public function test_legal_transition_updates_status_and_records_event(): void
    {
        $branch = $this->branch('AAD');
        $admin = $this->user('branch-admin', $branch);
        $shipment = $this->shipment($branch, 'booked');

        $this->actingAs($admin)->patchJson("/api/shipments/{$shipment->id}/statusUpdate", ['status' => 'picked_up'])
            ->assertOk();

        $this->assertSame('picked_up', $shipment->fresh()->status);
        $this->assertSame(1, ShipmentEvent::where('shipment_id', $shipment->id)->where('event_type', 'picked_up')->count());
    }

    public function test_in_transit_requires_a_destination(): void
    {
        $branch = $this->branch('AAE');
        $admin = $this->user('branch-admin', $branch);
        $shipment = $this->shipment($branch, 'picked_up');

        $this->actingAs($admin)->patchJson("/api/shipments/{$shipment->id}/statusUpdate", ['status' => 'in_transit'])
            ->assertStatus(422)->assertJsonValidationErrors('destination_type');
        $this->assertSame('picked_up', $shipment->fresh()->status);
    }

    public function test_other_branch_cannot_update_a_shipment(): void
    {
        $a = $this->branch('AAF');
        $b = $this->branch('AAG');
        $shipment = $this->shipment($a, 'booked');

        $this->actingAs($this->user('branch-admin', $b))
            ->patchJson("/api/shipments/{$shipment->id}/statusUpdate", ['status' => 'picked_up'])
            ->assertForbidden();
    }

    public function test_awb_numbers_are_unique_and_sequential_per_branch(): void
    {
        $branch = $this->branch('BPD');
        $first = $this->shipment($branch, 'booked');
        $second = $this->shipment($branch, 'booked');
        $other = $this->shipment($this->branch('XYZ'), 'booked');

        $prefix = 'BPD' . now()->format('ym');
        $this->assertSame($prefix . '00001', $first->awb_number);
        $this->assertSame($prefix . '00002', $second->awb_number);
        $this->assertSame('XYZ' . now()->format('ym') . '00001', $other->awb_number);
    }

    public function test_manual_awb_is_kept(): void
    {
        $branch = $this->branch('AAH');
        $this->assertSame('MANUAL1', $this->shipment($branch, 'booked', 'MANUAL1')->awb_number);
    }
}
