<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Shipment {{ $shipment->awb_number }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 12px;
      color: #111;
      background: #fff;
    }

    .page {
      padding: 28px 32px;
    }

    /* ── Header ── */
    .header {
      border-bottom: 2px solid #111;
      padding-bottom: 12px;
      margin-bottom: 16px;
    }
    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    .company-name {
      font-size: 22px;
      font-weight: bold;
      letter-spacing: 0.5px;
    }
    .company-tagline {
      font-size: 10px;
      color: #555;
      margin-top: 2px;
    }
    {{-- Branch details block — uncomment when client confirms --}}
    {{-- .branch-info { text-align: right; font-size: 10px; color: #444; line-height: 1.6; } --}}

    .awb-block {
      text-align: right;
    }
    .awb-label {
      font-size: 10px;
      color: #555;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .awb-number {
      font-size: 20px;
      font-weight: bold;
      letter-spacing: 1.5px;
      font-family: 'Courier New', monospace;
    }
    .status-badge {
      display: inline-block;
      margin-top: 4px;
      padding: 2px 10px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: bold;
      text-transform: uppercase;
      background: #e5e7eb;
      color: #374151;
    }

    /* ── Section ── */
    .section {
      margin-bottom: 14px;
    }
    .section-title {
      font-size: 10px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #fff;
      background: #0f766e;
      padding: 4px 10px;
      margin-bottom: 8px;
    }
    .section-body {
      padding: 0 4px;
    }

    /* ── Two column layout ── */
    .two-col {
      display: table;
      width: 100%;
      border-collapse: collapse;
    }
    .col {
      display: table-cell;
      width: 50%;
      vertical-align: top;
      padding-right: 16px;
    }
    .col:last-child { padding-right: 0; }

    /* ── Label-value ── */
    .lv { margin-bottom: 6px; }
    .lv-label {
      font-size: 9px;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    .lv-value {
      font-size: 11px;
      color: #111;
      margin-top: 1px;
    }

    /* ── Table ── */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 11px;
    }
    th {
      background: #f3f4f6;
      text-align: left;
      padding: 5px 8px;
      font-size: 9px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      color: #374151;
      border: 1px solid #e5e7eb;
    }
    td {
      padding: 5px 8px;
      border: 1px solid #e5e7eb;
      color: #111;
    }
    tr:nth-child(even) td { background: #f9fafb; }

    /* ── Charges summary ── */
    .charges-grid {
      display: table;
      width: 100%;
    }
    .charges-row {
      display: table-row;
    }
    .charges-cell {
      display: table-cell;
      width: 25%;
      padding: 4px 6px;
    }

    .totals-row {
      display: table;
      width: 100%;
      margin-top: 10px;
      border-top: 1px solid #e5e7eb;
      padding-top: 10px;
    }
    .total-box {
      display: table-cell;
      width: 33.33%;
      text-align: center;
      padding: 6px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
    }
    .total-box.grand {
      background: #f0fdf4;
      border-color: #bbf7d0;
    }
    .total-label {
      font-size: 9px;
      color: #6b7280;
      text-transform: uppercase;
    }
    .total-value {
      font-size: 14px;
      font-weight: bold;
      margin-top: 2px;
    }
    .total-box.grand .total-value { color: #15803d; }

    /* ── Signature ── */
    .signature-section {
      margin-top: 32px;
      border-top: 1px solid #e5e7eb;
      padding-top: 16px;
      display: table;
      width: 100%;
    }
    .sig-cell {
      display: table-cell;
      width: 50%;
      padding-right: 32px;
    }
    .sig-cell:last-child { padding-right: 0; }
    .sig-line {
      border-bottom: 1px solid #111;
      height: 36px;
      margin-bottom: 4px;
    }
    .sig-label {
      font-size: 9px;
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    /* ── Footer ── */
    .footer {
      margin-top: 20px;
      border-top: 1px solid #e5e7eb;
      padding-top: 8px;
      font-size: 9px;
      color: #9ca3af;
      text-align: center;
    }
  </style>
</head>
<body>
<div class="page">

  {{-- ── HEADER ── --}}
  <div class="header">
    <div class="header-top">
      <div>
        <div class="company-name">VK Enterprises</div>
        <div class="company-tagline">Courier &amp; Logistics Services</div>

        {{-- Branch details — uncomment when client confirms --}}
        {{--
        <div style="margin-top: 6px; font-size: 10px; color: #444; line-height: 1.6;">
          <strong>Branch:</strong> {{ $shipment->branch?->name }}<br/>
          {{ $shipment->branch?->address }}<br/>
          {{ $shipment->branch?->phone }}
        </div>
        --}}
      </div>
      <div class="awb-block">
        <div class="awb-label">AWB Number</div>
        <div class="awb-number">{{ $shipment->awb_number }}</div>
        <div>
          <span class="status-badge">{{ strtoupper(str_replace('_', ' ', $shipment->status)) }}</span>
        </div>
        @if($shipment->booked_at)
          <div style="font-size: 9px; color: #6b7280; margin-top: 4px;">
            Booked: {{ \Carbon\Carbon::parse($shipment->booked_at)->format('d M Y, h:i A') }}
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- ── SHIPPER & CONSIGNEE ── --}}
  <div class="two-col">

    @if($config['show_shipper_details'])
    <div class="col">
      <div class="section">
        <div class="section-title">Shipper</div>
        <div class="section-body">
          <div class="lv">
            <div class="lv-label">Name</div>
            <div class="lv-value">{{ $shipment->shipper_name }}</div>
          </div>
          @if($shipment->shipper_company)
          <div class="lv">
            <div class="lv-label">Company</div>
            <div class="lv-value">{{ $shipment->shipper_company }}</div>
          </div>
          @endif
          <div class="lv">
            <div class="lv-label">Phone</div>
            <div class="lv-value">{{ $shipment->shipper_phone }}</div>
          </div>
          <div class="lv">
            <div class="lv-label">Address</div>
            <div class="lv-value">
              {{ implode(', ', array_filter([
                  $shipment->shipper_address_line1,
                  $shipment->shipper_address_line2,
                  $shipment->shipper_city,
                  $shipment->shipper_state,
                  $shipment->shipper_pincode,
              ])) }}
            </div>
          </div>
          @if($config['show_shipper_gst'] && $shipment->shipper_gst)
          <div class="lv">
            <div class="lv-label">GST</div>
            <div class="lv-value">{{ $shipment->shipper_gst }}</div>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    @if($config['show_consignee_details'])
    <div class="col">
      <div class="section">
        <div class="section-title">Consignee</div>
        <div class="section-body">
          <div class="lv">
            <div class="lv-label">Name</div>
            <div class="lv-value">{{ $shipment->consignee_name }}</div>
          </div>
          <div class="lv">
            <div class="lv-label">Phone</div>
            <div class="lv-value">{{ $shipment->consignee_phone }}</div>
          </div>
          <div class="lv">
            <div class="lv-label">Address</div>
            <div class="lv-value">
              {{ implode(', ', array_filter([
                  $shipment->consignee_address,
                  $shipment->consignee_city,
                  $shipment->consignee_state,
                  $shipment->consignee_pincode,
              ])) }}
            </div>
          </div>
          @if($config['show_consignee_gst'] && $shipment->consignee_gst)
          <div class="lv">
            <div class="lv-label">GST</div>
            <div class="lv-value">{{ $shipment->consignee_gst }}</div>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

  </div>

  {{-- ── SERVICE DETAILS ── --}}
  <div class="section">
    <div class="section-title">Service Details</div>
    <div class="section-body">
      <div class="two-col">
        <div class="col">
          <div class="lv">
            <div class="lv-label">Service Type</div>
            <div class="lv-value">{{ $shipment->service_type }}</div>
          </div>
          <div class="lv">
            <div class="lv-label">Service</div>
            <div class="lv-value">{{ $shipment->service }}</div>
          </div>
          <div class="lv">
            <div class="lv-label">Payment Mode</div>
            <div class="lv-value">{{ $shipment->payment_mode }}</div>
          </div>
        </div>
        <div class="col">
          @if($shipment->customer_ref)
          <div class="lv">
            <div class="lv-label">Customer Ref</div>
            <div class="lv-value">{{ $shipment->customer_ref }}</div>
          </div>
          @endif
          @if($shipment->parcel_content)
          <div class="lv">
            <div class="lv-label">Content</div>
            <div class="lv-value">{{ $shipment->parcel_content }}</div>
          </div>
          @endif
          @if($shipment->in_favour_of)
          <div class="lv">
            <div class="lv-label">In Favour Of</div>
            <div class="lv-value">{{ $shipment->in_favour_of }} ({{ $shipment->payable_at }})</div>
          </div>
          @endif
          @if($shipment->collectable_amount)
          <div class="lv">
            <div class="lv-label">Collectable Amount</div>
            <div class="lv-value">₹{{ $shipment->collectable_amount }}</div>
          </div>
          @endif
        </div>
      </div>
      @if($config['show_special_instructions'] && $shipment->special_instructions)
      <div class="lv" style="margin-top: 6px;">
        <div class="lv-label">Special Instructions</div>
        <div class="lv-value">{{ $shipment->special_instructions }}</div>
      </div>
      @endif
    </div>
  </div>

  {{-- ── PARCELS ── --}}
  @if($config['show_parcel_dimensions'] && $shipment->parcels->count() > 0)
  <div class="section">
    <div class="section-title">{{ $shipment->service === 'Document' ? 'Document Dimensions' : 'Parcels' }}</div>
    <div class="section-body">
      <table>
        <thead>
          <tr>
            <th>Length</th>
            <th>Width</th>
            <th>Height</th>
            <th>Weight (kg)</th>
            <th>Vol. Weight (kg)</th>
            @if($shipment->service === 'Parcel')
            <th>Boxes</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($shipment->parcels as $parcel)
          <tr>
            <td>{{ $parcel->length }}</td>
            <td>{{ $parcel->width }}</td>
            <td>{{ $parcel->height }}</td>
            <td>{{ $parcel->weight }}</td>
            <td>{{ $parcel->vol_weight }}</td>
            @if($shipment->service === 'Parcel')
            <td>{{ $parcel->num_boxes }}</td>
            @endif
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ── INVOICES ── --}}
  @if($config['show_invoice_details'] && $shipment->invoices->count() > 0)
  <div class="section">
    <div class="section-title">Invoices</div>
    <div class="section-body">
      <table>
        <thead>
          <tr>
            <th>Invoice Number</th>
            <th>Amount</th>
            @if($config['show_eway_bill'])
            <th>E-Way Bill</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($shipment->invoices as $invoice)
          <tr>
            <td>{{ $invoice->invoice_number }}</td>
            <td>₹{{ $invoice->invoice_amount }}</td>
            @if($config['show_eway_bill'])
            <td>{{ $invoice->eway_bill ?? '—' }}</td>
            @endif
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- ── CHARGES ── --}}
  @if($shipment->charges)
  <div class="section">
    <div class="section-title">Charges</div>
    <div class="section-body">

      @if($config['show_charges_breakdown'])
      {{-- Full breakdown --}}
      <div class="charges-grid">
        @php
          $chargeItems = [
            'Freight'          => $shipment->charges->freight,
            'Fuel'             => $shipment->charges->fuel,
            'AWB Fee'          => $shipment->charges->awb_fee,
            'FOV'              => $shipment->charges->fov,
            'FOD'              => $shipment->charges->fod,
            'DOD'              => $shipment->charges->dod,
            'ODA'              => $shipment->charges->oda,
            'Handling'         => $shipment->charges->handling,
            'DCC'              => $shipment->charges->dcc,
            'Pickup Charges'   => $shipment->charges->pickup_charges,
            'Delivery Charges' => $shipment->charges->delivery_charges,
          ];
          if($shipment->charges->insurance_type === 'carrier') {
            $chargeItems['Carrier Insurance'] = $shipment->charges->carrier_insurance;
          }
          // Filter out zero/null values
          $chargeItems = array_filter($chargeItems, fn($v) => $v > 0);
          $chunks = array_chunk(array_keys($chargeItems), 4, true);
        @endphp

        @foreach($chunks as $chunkKeys)
        <div class="charges-row">
          @foreach($chunkKeys as $label)
          <div class="charges-cell">
            <div class="lv-label">{{ $label }}</div>
            <div class="lv-value">₹{{ $chargeItems[$label] }}</div>
          </div>
          @endforeach
          {{-- Pad empty cells to maintain grid --}}
          @for($i = count($chunkKeys); $i < 4; $i++)
          <div class="charges-cell"></div>
          @endfor
        </div>
        @endforeach
      </div>

      {{-- Totals --}}
      <div class="totals-row">
        <div class="total-box">
          <div class="total-label">Total</div>
          <div class="total-value">₹{{ $shipment->charges->total }}</div>
        </div>
        <div class="total-box">
          <div class="total-label">GST @18%</div>
          <div class="total-value">₹{{ $shipment->charges->gst }}</div>
        </div>
        <div class="total-box grand">
          <div class="total-label">Grand Total</div>
          <div class="total-value">₹{{ $shipment->charges->grand_total }}</div>
        </div>
      </div>

      @elseif($config['show_grand_total_only'])
      {{-- Simplified Total + GST = Grand Total --}}
      <div class="totals-row">
        <div class="total-box">
          <div class="total-label">Total</div>
          <div class="total-value">₹{{ $shipment->charges->total }}</div>
        </div>
        <div class="total-box">
          <div class="total-label">GST @18%</div>
          <div class="total-value">₹{{ $shipment->charges->gst }}</div>
        </div>
        <div class="total-box grand">
          <div class="total-label">Grand Total</div>
          <div class="total-value">₹{{ $shipment->charges->grand_total }}</div>
        </div>
      </div>
      @endif

    </div>
  </div>
  @endif

  {{-- ── SIGNATURE ── --}}
  <div class="signature-section">
    <div class="sig-cell">
      <div class="sig-line"></div>
      <div class="sig-label">Receiver's Signature</div>
    </div>
    <div class="sig-cell">
      <div class="sig-line"></div>
      <div class="sig-label">Authorised Signatory</div>
    </div>
  </div>

  {{-- ── FOOTER ── --}}
  <div class="footer">
    Generated on {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp;
    AWB: {{ $shipment->awb_number }} &nbsp;|&nbsp;
    VK Enterprises — Courier &amp; Logistics
  </div>

</div>
</body>
</html>