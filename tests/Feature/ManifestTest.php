<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Manifest;
use App\Models\ShipmentEvent;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCourierFixtures;
use Tests\TestCase;

class ManifestTest extends TestCase
{
    use RefreshDatabase, BuildsCourierFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_pickup_manifest_moves_shipments_to_picked_up_and_logs_events(): void
    {
        $branch = $this->makeBranch('MFA');
        $admin = $this->makeUser('branch-admin', $branch);
        $a = $this->makeShipment($branch, 'booked');
        $b = $this->makeShipment($branch, 'booked');

        $res = $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'pickup', 'shipment_ids' => [$a->id, $b->id],
        ])->assertCreated();

        $this->assertSame('picked_up', $a->fresh()->status);
        $this->assertSame('picked_up', $b->fresh()->status);
        $manifest = Manifest::findOrFail($res->json('data.id'));
        $this->assertSame(2, $manifest->shipments()->count());
        $this->assertSame('open', $manifest->status);
        $this->assertMatchesRegularExpression('/^PKP-' . now()->format('Ym') . '-0001$/', $manifest->manifest_number);
        $this->assertSame(2, ShipmentEvent::where('event_type', 'picked_up')->count());
    }

    public function test_shipments_in_wrong_status_reject_the_whole_manifest(): void
    {
        $branch = $this->makeBranch('MFB');
        $admin = $this->makeUser('branch-admin', $branch);
        $ok = $this->makeShipment($branch, 'booked');
        $bad = $this->makeShipment($branch, 'in_transit');

        $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'pickup', 'shipment_ids' => [$ok->id, $bad->id],
        ])->assertStatus(422);

        $this->assertSame('booked', $ok->fresh()->status);
        $this->assertSame(0, Manifest::count());
    }

    public function test_cannot_manifest_another_branchs_shipments(): void
    {
        $mine = $this->makeBranch('MFC');
        $other = $this->makeBranch('MFD');
        $admin = $this->makeUser('branch-admin', $mine);
        $theirs = $this->makeShipment($other, 'booked');

        $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'pickup', 'shipment_ids' => [$theirs->id],
        ])->assertStatus(422);
        $this->assertSame('booked', $theirs->fresh()->status);
    }

    public function test_dispatch_requires_a_destination_and_records_it(): void
    {
        $branch = $this->makeBranch('MFE');
        $hub = $this->makeWarehouse('HUB');
        $admin = $this->makeUser('branch-admin', $branch);
        $s = $this->makeShipment($branch, 'picked_up');

        $this->actingAs($admin)->postJson('/api/manifests', ['type' => 'dispatch', 'shipment_ids' => [$s->id]])
            ->assertStatus(422)->assertJsonValidationErrors('destination_type');

        $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'dispatch', 'shipment_ids' => [$s->id],
            'destination_type' => Warehouse::class, 'destination_id' => $hub->id,
        ])->assertCreated();

        $this->assertSame('in_transit', $s->fresh()->status);
        $event = ShipmentEvent::where('shipment_id', $s->id)->where('event_type', 'in_transit')->firstOrFail();
        $this->assertSame(Warehouse::class, $event->destination_type);
        $this->assertSame($hub->id, (int) $event->destination_id);
    }

    public function test_inbound_only_lists_shipments_headed_to_my_location(): void
    {
        $dest = $this->makeBranch('MFF');
        $elsewhere = $this->makeBranch('MFG');
        $origin = $this->makeBranch('MFH');
        $admin = $this->makeUser('branch-admin', $dest);

        $toMe = $this->makeShipment($origin, 'in_transit');
        $toOther = $this->makeShipment($origin, 'in_transit');
        foreach ([[$toMe, $dest], [$toOther, $elsewhere]] as [$shipment, $to]) {
            ShipmentEvent::create(['shipment_id' => $shipment->id, 'event_type' => 'in_transit', 'entity_type' => Branch::class,
                'entity_id' => $origin->id, 'destination_type' => Branch::class, 'destination_id' => $to->id, 'created_by' => $admin->id]);
        }

        $ids = collect($this->actingAs($admin)->getJson('/api/manifests/eligible-shipments?type=inbound')->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$toMe->id], $ids->all());

        $this->actingAs($admin)->postJson('/api/manifests', ['type' => 'inbound', 'shipment_ids' => [$toMe->id]])->assertCreated();
        $this->assertSame('at_branch', $toMe->fresh()->status);
    }

    public function test_delivery_manifest_assigns_the_agent_and_checks_their_branch(): void
    {
        $branch = $this->makeBranch('MFI');
        $other = $this->makeBranch('MFJ');
        $admin = $this->makeUser('branch-admin', $branch);
        $agent = $this->makeUser('branch-delivery', $branch);
        $foreignAgent = $this->makeUser('branch-delivery', $other);
        $s = $this->makeShipment($branch, 'at_branch');
        ShipmentEvent::create(['shipment_id' => $s->id, 'event_type' => 'at_branch', 'entity_type' => Branch::class,
            'entity_id' => $branch->id, 'created_by' => $admin->id]);

        $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'delivery', 'shipment_ids' => [$s->id], 'delivery_agent_id' => $foreignAgent->id,
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/manifests', [
            'type' => 'delivery', 'shipment_ids' => [$s->id], 'delivery_agent_id' => $agent->id,
        ])->assertCreated();

        $this->assertSame('out_for_delivery', $s->fresh()->status);
        $this->assertSame($agent->id, $s->assignments()->first()->assigned_to);
    }

    public function test_manifest_can_be_closed_once_by_its_origin(): void
    {
        $branch = $this->makeBranch('MFK');
        $admin = $this->makeUser('branch-admin', $branch);
        $s = $this->makeShipment($branch, 'booked');
        $id = $this->actingAs($admin)->postJson('/api/manifests', ['type' => 'pickup', 'shipment_ids' => [$s->id]])->json('data.id');

        $this->actingAs($admin)->postJson("/api/manifests/{$id}/close")->assertOk();
        $this->assertSame('closed', Manifest::find($id)->status);
        $this->actingAs($admin)->postJson("/api/manifests/{$id}/close")->assertStatus(422);

        $outsider = $this->makeUser('branch-admin', $this->makeBranch('MFL'));
        $this->actingAs($outsider)->getJson("/api/manifests/{$id}")->assertForbidden();
    }
}
