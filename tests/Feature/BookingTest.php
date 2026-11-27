<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCourierFixtures;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase, BuildsCourierFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'status' => 'booked',
            'customer' => ['customerType' => 'cash'],
            'service' => ['service' => 'Parcel', 'serviceType' => 'Surface'],
            'shipper' => ['shipperName' => 'Shipper'],
            'consignee' => ['consigneeName' => 'Consignee', 'receiverName' => 'Receiver', 'consigneeState' => 1],
            'parcels' => [['length' => 10, 'width' => 10, 'height' => 10, 'weight' => 5, 'numBoxes' => 2, 'volWeight' => 3]],
            'invoices' => [['invoiceNumber' => 'INV-1', 'invoiceAmount' => 1000]],
            'rates' => ['freight' => 100, 'fuel' => 10, 'awbFee' => 20, 'total' => 130, 'gst' => 23.4, 'grandTotal' => 153.4],
        ], $override);
    }

    public function test_booking_creates_shipment_parcels_charges_event_and_cash_invoice(): void
    {
        $branch = $this->makeBranch('BKA');
        $admin = $this->makeUser('branch-admin', $branch);

        $res = $this->actingAs($admin)->postJson('/api/shipments', $this->payload())->assertCreated();

        $shipment = Shipment::findOrFail($res->json('data.id'));
        $this->assertSame($branch->id, $shipment->branch_id);
        $this->assertSame('booked', $shipment->status);
        $this->assertNotNull($shipment->booked_at);
        $this->assertStringStartsWith('BKA' . now()->format('ym'), $shipment->awb_number);
        $this->assertSame(1, $shipment->parcels()->count());
        $this->assertSame(1, $shipment->shipmentInvoices()->count());
        $this->assertSame(1, $shipment->charges()->count());
        $this->assertSame(1, $shipment->events()->where('event_type', 'booked')->count());

        // cash booking is invoiced immediately: 130 + 18% GST = 153.40, intra-state => CGST+SGST
        $invoice = Invoice::firstOrFail();
        $this->assertSame('cash', $invoice->type);
        $this->assertSame('finalized', $invoice->status);
        $this->assertEquals(130.00, $invoice->subtotal);
        $this->assertEquals(23.40, (float) $invoice->cgst + (float) $invoice->sgst + (float) $invoice->igst);
        $this->assertEquals(153.40, $invoice->grand_total);
        $this->assertSame($shipment->id, $invoice->shipments()->first()->id);
    }

    public function test_draft_gets_no_invoice_and_no_booked_at(): void
    {
        $admin = $this->makeUser('branch-admin', $this->makeBranch('BKB'));

        $res = $this->actingAs($admin)->postJson('/api/shipments', $this->payload(['status' => 'draft']))->assertCreated();

        $this->assertNull(Shipment::findOrFail($res->json('data.id'))->booked_at);
        $this->assertSame(0, Invoice::count());
    }

    public function test_corporate_booking_is_not_invoiced_immediately(): void
    {
        $branch = $this->makeBranch('BKC');
        $admin = $this->makeUser('branch-admin', $branch);
        $customer = $this->makeCustomer($branch);

        $this->actingAs($admin)->postJson('/api/shipments', $this->payload([
            'customer' => ['customerType' => 'corporate', 'customerId' => $customer->id],
        ]))->assertCreated();

        $this->assertSame(0, Invoice::count());
    }

    public function test_validation_rejects_bad_input_and_persists_nothing(): void
    {
        $admin = $this->makeUser('branch-admin', $this->makeBranch('BKD'));

        $this->actingAs($admin)->postJson('/api/shipments', $this->payload(['parcels' => [['weight' => 0]]]))
            ->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/shipments', $this->payload(['shipper' => ['shipperPhone' => '12345']]))
            ->assertStatus(422)->assertJsonValidationErrors('shipper.shipperPhone');
        $this->actingAs($admin)->postJson('/api/shipments', $this->payload(['service' => ['paymentMode' => 'COD']]))
            ->assertStatus(422);

        $this->assertSame(0, Shipment::count());
    }

    public function test_duplicate_tracking_number_is_rejected(): void
    {
        $admin = $this->makeUser('branch-admin', $this->makeBranch('BKE'));
        $p = $this->payload(['service' => ['trackingNumber' => 'DUP1']]);

        $this->actingAs($admin)->postJson('/api/shipments', $p)->assertCreated();
        $this->actingAs($admin)->postJson('/api/shipments', $p)->assertStatus(422);
    }

    public function test_head_office_must_choose_a_branch(): void
    {
        $super = $this->makeUser('super-admin');

        $this->actingAs($super)->postJson('/api/shipments', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('branchId');

        $branch = $this->makeBranch('BKF');
        $res = $this->actingAs($super)->postJson('/api/shipments', $this->payload(['branchId' => $branch->id]))->assertCreated();
        $this->assertSame($branch->id, $res->json('data.branch_id'));
    }

    public function test_warehouse_users_cannot_book(): void
    {
        $wh = $this->makeWarehouse('WHA');

        $this->actingAs($this->makeUser('warehouse-admin', $wh))
            ->postJson('/api/shipments', $this->payload())->assertForbidden();
    }

    public function test_only_draft_or_booked_shipments_can_be_edited(): void
    {
        $branch = $this->makeBranch('BKG');
        $admin = $this->makeUser('branch-admin', $branch);
        $shipment = $this->makeShipment($branch, 'in_transit');

        $this->actingAs($admin)->putJson("/api/shipments/{$shipment->id}", $this->payload())->assertForbidden();
    }
}
