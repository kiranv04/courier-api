<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCourierFixtures;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase, BuildsCourierFixtures;

    private Branch $branch;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
        $this->branch = $this->makeBranch('INA');   // branch location is in state 1 (Karnataka)
        $this->customer = $this->makeCustomer($this->branch);
    }

    /** Shipment with one charge row: freight 100, fuel 10, FOD 5 => subtotal 115, GST 20.70. */
    private function chargedShipment(int $consigneeState = 1, array $extra = []): Shipment
    {
        $s = $this->makeShipment($this->branch, 'booked', $extra + [
            'customer_id' => $this->customer->id, 'consignee_state_id' => $consigneeState,
        ]);
        $s->charges()->create(['freight' => 100, 'fuel' => 10, 'fod' => 5, 'total' => 115, 'gst' => 20.70, 'grand_total' => 135.70]);
        return $s;
    }

    private function body(array $shipments, array $override = []): array
    {
        return $override + [
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'from_date' => now()->subDay()->toDateString(),
            'to_date' => now()->addDay()->toDateString(),
            'shipment_ids' => collect($shipments)->pluck('id')->all(),
        ];
    }

    public function test_intra_state_invoice_splits_gst_into_cgst_and_sgst(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $a = $this->chargedShipment(1);
        $b = $this->chargedShipment(1);

        $res = $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$a, $b]))->assertCreated();

        $inv = Invoice::findOrFail($res->json('data.id'));
        $this->assertSame('corporate', $inv->type);
        $this->assertSame('finalized', $inv->status);
        $this->assertEquals(200.00, $inv->freight_vas);
        $this->assertEquals(20.00, $inv->fuel_surcharge);
        $this->assertEquals(10.00, $inv->fod_dod);
        $this->assertEquals(230.00, $inv->subtotal);
        $this->assertEquals(20.70, $inv->cgst);
        $this->assertEquals(20.70, $inv->sgst);
        $this->assertEquals(0, $inv->igst);
        $this->assertEquals(271.40, $inv->grand_total);
        $this->assertSame(2, $inv->shipments()->count());
    }

    public function test_inter_state_invoice_charges_igst(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $s = $this->chargedShipment(2);

        $res = $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$s]))->assertCreated();

        $inv = Invoice::findOrFail($res->json('data.id'));
        $this->assertEquals(0, $inv->cgst);
        $this->assertEquals(0, $inv->sgst);
        $this->assertEquals(20.70, $inv->igst);
        $this->assertEquals(135.70, $inv->grand_total);
    }

    public function test_a_shipment_cannot_be_invoiced_twice(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $s = $this->chargedShipment();

        $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$s]))->assertCreated();
        $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$s]))->assertStatus(422);

        $this->assertSame(1, Invoice::count());
    }

    public function test_draft_cancelled_and_out_of_period_shipments_are_rejected(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);

        foreach ([
            $this->chargedShipment(1, ['status' => 'draft']),
            $this->chargedShipment(1, ['status' => 'cancelled']),
            $this->chargedShipment(1, ['booked_at' => now()->subMonths(2)]),
        ] as $bad) {
            $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$bad]))->assertStatus(422);
        }
        $this->assertSame(0, Invoice::count());
    }

    public function test_other_customers_shipments_are_rejected(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $other = $this->makeCustomer($this->branch, 'corporate', 'C2');
        $s = $this->chargedShipment(1, ['customer_id' => $other->id]);

        $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$s]))->assertStatus(422);
    }

    public function test_branch_admin_can_only_invoice_their_own_branch(): void
    {
        $rival = $this->makeBranch('INB');
        $rivalAdmin = $this->makeUser('branch-admin', $rival);
        $s = $this->chargedShipment();

        $this->actingAs($rivalAdmin)->postJson('/api/invoices/corporate', $this->body([$s]))
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
    }

    public function test_preview_excludes_invoiced_shipments_and_sums_charges(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $invoiced = $this->chargedShipment();
        $open = $this->chargedShipment();
        $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$invoiced]))->assertCreated();

        $q = http_build_query(['customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'from_date' => now()->subDay()->toDateString(), 'to_date' => now()->addDay()->toDateString()]);
        $res = $this->actingAs($admin)->getJson("/api/invoices/preview-corporate?{$q}")->assertOk();

        $this->assertSame([$open->id], collect($res->json('shipments'))->pluck('id')->all());
        $this->assertEquals(115.00, $res->json('summary.subtotal'));
        $this->assertEquals(135.70, $res->json('summary.grand_total'));
    }

    public function test_invoice_numbers_are_sequential_within_the_financial_year(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $first = $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$this->chargedShipment()]))->json('data.invoice_number');
        $second = $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$this->chargedShipment()]))->json('data.invoice_number');

        $this->assertMatchesRegularExpression('/^VK-\d{4}-000001$/', $first);
        $this->assertSame(substr($first, 0, -6) . '000002', $second);
    }

    public function test_invoice_list_is_scoped_to_the_callers_branch(): void
    {
        $admin = $this->makeUser('branch-admin', $this->branch);
        $this->actingAs($admin)->postJson('/api/invoices/corporate', $this->body([$this->chargedShipment()]))->assertCreated();
        $id = Invoice::first()->id;

        $rivalAdmin = $this->makeUser('branch-admin', $this->makeBranch('INC'));
        $this->assertCount(0, $this->actingAs($rivalAdmin)->getJson('/api/invoices')->assertOk()->json('data.data'));
        $this->actingAs($rivalAdmin)->getJson("/api/invoices/{$id}")->assertForbidden();
        $this->assertCount(1, $this->actingAs($admin)->getJson('/api/invoices')->assertOk()->json('data.data'));
    }
}
