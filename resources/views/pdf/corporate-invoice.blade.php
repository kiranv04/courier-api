<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tax Invoice – {{ $vkInvoice->invoice_number }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 10px;
      color: #111;
      background: #fff;
    }

    .page {
      padding: 18px 22px;
    }

    /* ── Page break ── */
    .page-break {
      page-break-after: always;
    }

    /* ── Invoice outer border ── */
    .invoice-box {
      border: 1.5px solid #333;
      width: 100%;
    }

    /* ── Page header (repeated on both pages) ── */
    .page-header-bar {
      background: #d0d0d0;
      padding: 5px 12px;
      font-size: 13px;
      font-weight: bold;
      border-bottom: 1.5px solid #333;
    }

    /* ── Top section: company | QR area | details ── */
    .top-row {
      display: table;
      width: 100%;
      border-bottom: 1px solid #333;
    }
    .top-company {
      display: table-cell;
      width: 32%;
      vertical-align: top;
      padding: 10px 12px;
      border-right: 1px solid #ccc;
    }
    .top-middle {
      display: table-cell;
      width: 28%;
      vertical-align: middle;
      text-align: center;
      padding: 10px 8px;
      border-right: 1px solid #ccc;
      font-size: 9px;
      color: #555;
    }
    .top-details {
      display: table-cell;
      width: 40%;
      vertical-align: top;
    }

    .company-name-lg {
      font-size: 12px;
      font-weight: bold;
    }
    .company-addr {
      font-size: 9px;
      color: #333;
      line-height: 1.55;
      margin-top: 3px;
    }
    .gstin-line {
      font-size: 9px;
      margin-top: 5px;
    }
    .gstin-line strong { font-weight: bold; }

    .customer-block {
      margin-top: 10px;
      padding-top: 8px;
      border-top: 1px solid #ccc;
    }
    .block-label {
      font-size: 9px;
      font-weight: bold;
      color: #444;
      margin-bottom: 3px;
    }
    .customer-name {
      font-size: 10px;
      font-weight: bold;
    }
    .customer-addr {
      font-size: 9px;
      color: #333;
      line-height: 1.55;
      margin-top: 2px;
    }

    /* Detail rows inside top-details */
    .drow {
      display: table;
      width: 100%;
      border-bottom: 1px solid #e8e8e8;
    }
    .drow:last-child { border-bottom: none; }
    .dk {
      display: table-cell;
      width: 44%;
      padding: 4px 10px;
      font-size: 9.5px;
      color: #444;
      font-weight: bold;
      vertical-align: middle;
    }
    .dv {
      display: table-cell;
      width: 56%;
      padding: 4px 10px;
      font-size: 9.5px;
      color: #111;
      vertical-align: middle;
    }

    /* ── Shipment details table ── */
    .section-heading {
      text-align: center;
      font-size: 11px;
      font-weight: bold;
      padding: 6px;
      border-top: 1px solid #333;
      border-bottom: 1px solid #333;
      background: #f5f5f5;
    }

    table.shipments {
      width: 100%;
      border-collapse: collapse;
      font-size: 9px;
    }
    table.shipments th {
      background: #e8e8e8;
      border: 1px solid #ccc;
      padding: 4px 6px;
      text-align: left;
      font-weight: bold;
    }
    table.shipments td {
      border: 1px solid #ddd;
      padding: 4px 6px;
      vertical-align: middle;
    }
    table.shipments tr:nth-child(even) td {
      background: #fafafa;
    }
    table.shipments tfoot td {
      font-weight: bold;
      border-top: 1.5px solid #333;
      background: #f0f0f0;
    }

    /* ── Page 2 totals ── */
    .totals-section {
      padding: 14px 16px;
      border-top: 1.5px solid #333;
    }
    .totals-table {
      width: 55%;
      margin-left: auto;
      border-collapse: collapse;
    }
    .totals-table td {
      padding: 4px 10px;
      font-size: 10px;
    }
    .totals-table .t-label { color: #333; }
    .totals-table .t-value { text-align: right; font-weight: bold; }
    .totals-table .t-divider td {
      border-top: 1px solid #ccc;
      padding-top: 6px;
    }
    .totals-table .t-grand td {
      font-size: 11px;
      font-weight: bold;
      border-top: 1.5px solid #333;
      border-bottom: 1.5px solid #333;
      padding: 5px 10px;
    }

    .amount-words-line {
      margin-top: 8px;
      font-size: 9.5px;
      font-weight: bold;
      text-transform: uppercase;
      border: 1px solid #ccc;
      padding: 5px 10px;
      background: #fafafa;
    }

    /* Signatory is now inline via style attributes — no class needed */

    /* ── T&C / footer ── */
    .tnc-section {
      margin-top: 14px;
      border-top: 1.5px solid #333;
    }
    .tnc-heading {
      background: #d0d0d0;
      text-align: center;
      font-size: 10px;
      font-weight: bold;
      padding: 4px;
    }
    .tnc-row {
      display: table;
      width: 100%;
      border-top: 1px solid #ddd;
    }
    .tnc-num {
      display: table-cell;
      width: 5%;
      padding: 4px 8px;
      vertical-align: top;
      font-size: 9px;
    }
    .tnc-text {
      display: table-cell;
      padding: 4px 8px;
      font-size: 9px;
      color: #333;
    }

    .footer-bar {
      border-top: 1.5px solid #333;
      padding: 6px 12px;
      text-align: center;
      font-size: 8px;
      color: #555;
      line-height: 1.7;
    }

    .page-num {
      text-align: center;
      font-size: 9px;
      color: #555;
      padding: 6px;
      border-top: 1px solid #e0e0e0;
    }
  </style>
</head>
<body>

{{-- ════════════════════════════════════════════
     PAGE 1 — Header + Shipment list
     ════════════════════════════════════════════ --}}
<div class="page">
<div class="invoice-box">

  {{-- Title bar --}}
  <div class="page-header-bar">Tax Invoice</div>

  {{-- Top info block --}}
  <div class="top-row">

    {{-- VK details + customer --}}
    <div class="top-company">
      <div class="company-name-lg">VK Enterprises</div>
      <div class="company-addr">
        @if($vkInvoice->branch)
          {{ $vkInvoice->branch->name }},<br/>
          {!! nl2br(e($vkInvoice->branch->address)) !!}
          @if($vkInvoice->branch->phone)<br/>Tel: {{ $vkInvoice->branch->phone }}@endif
        @endif
      </div>
      @if($vkGstin)
        <div class="gstin-line"><strong>GSTIN No:</strong> {{ $vkGstin }}</div>
      @endif

      <div class="customer-block">
        <div class="block-label">Customer Name &amp; Address</div>
        <div class="customer-name">{{ $vkInvoice->customer?->company_name ?? '—' }}</div>
        @php
          $billingAddr = $vkInvoice->customer?->addresses?->firstWhere('address_type', 'billing')
                      ?? $vkInvoice->customer?->addresses?->first();
        @endphp
        @if($billingAddr)
          <div class="customer-addr">
            {{ implode(', ', array_filter([
                $billingAddr->address_line1,
                $billingAddr->address_line2,
                $billingAddr->city,
                $billingAddr->pincode,
            ])) }}
          </div>
          @if($billingAddr->gst_number)
            <div class="gstin-line" style="margin-top:4px;">
              <strong>GSTIN No:</strong> {{ $billingAddr->gst_number }}
            </div>
          @endif
        @endif
        <div class="gstin-line" style="margin-top:3px;"><strong>PO No:</strong></div>
      </div>
    </div>

    {{-- Middle — placeholder for QR / blank ── --}}
    <div class="top-middle">
      {{-- QR code intentionally omitted --}}
    </div>

    {{-- Right: Invoice details --}}
    <div class="top-details">
      <div class="drow">
        <div class="dk">Customer A/C</div>
        <div class="dv">: {{ $vkInvoice->customer?->customer_code ?? '—' }}</div>
      </div>
      <div class="drow">
        <div class="dk">Invoice No.</div>
        <div class="dv">: {{ $vkInvoice->invoice_number }}</div>
      </div>
      <div class="drow">
        <div class="dk">Invoice Date</div>
        <div class="dv">: {{ \Carbon\Carbon::parse($vkInvoice->created_at)->format('d/m/Y') }}</div>
      </div>
      <div class="drow">
        <div class="dk">Period</div>
        <div class="dv">
          : {{ \Carbon\Carbon::parse($vkInvoice->from_date)->format('d/m/Y') }}
          – {{ \Carbon\Carbon::parse($vkInvoice->to_date)->format('d/m/Y') }}
        </div>
      </div>
      <div class="drow">
        <div class="dk">Description of Service</div>
        <div class="dv">: Courier Services</div>
      </div>
      <div class="drow">
        <div class="dk">RCM Applicable</div>
        <div class="dv">: N</div>
      </div>
      <div class="drow">
        <div class="dk">SAC Code</div>
        <div class="dv">: 996812</div>
      </div>
      <div class="drow">
        <div class="dk">State Code</div>
        <div class="dv">: {{ $branchGstCode ?? '—' }}</div>
      </div>
      <div class="drow">
        <div class="dk">Place of Supply</div>
        <div class="dv">: {{ $placeOfSupply ?? '—' }}</div>
      </div>
      <div class="drow">
        <div class="dk">GSTIN No.</div>
        <div class="dv">: {{ $vkGstin }}</div>
      </div>
    </div>

  </div>{{-- end top-row --}}

  {{-- Shipment Details heading --}}
  <div class="section-heading">Shipment Details</div>

  {{-- Shipment table --}}
  <table class="shipments">
    <thead>
      <tr>
        <th>SLNo</th>
        <th>P</th>
        <th>Awb No</th>
        <th>P/U Date</th>
        <th>Pcs</th>
        <th>Wgt</th>
        <th>St Date</th>
        <th>Time</th>
        <th>Revd By</th>
        <th>Destination</th>
        <th>Consignee</th>
        <th>Rate (₹)</th>
      </tr>
    </thead>
    <tbody>
      @php $shipmentTotal = 0; @endphp
      @foreach($vkInvoice->shipments as $i => $s)
        @php
          $pieces           = $s->parcels->sum('num_boxes');
          $chargeableWeight = $s->charges?->chargeable_weight ?? 0;
          $rowAmount        = $s->charges?->total ?? 0;
          $shipmentTotal   += $rowAmount;

          $pickupManifest = $s->manifests->first();  // already scoped to type=pickup
          $deliveredEvent = $s->events->first();      // already scoped to event_type=delivered

          $serviceCode = strtoupper(substr($s->service_type ?? $s->service ?? '', 0, 1));
        @endphp
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $serviceCode ?: '—' }}</td>
          <td>{{ $s->awb_number }}</td>
          <td>{{ $pickupManifest ? \Carbon\Carbon::parse($pickupManifest->created_at)->format('d/m/Y') : '—' }}</td>
          <td style="text-align:right;">{{ $pieces }}</td>
          <td style="text-align:right;">{{ number_format($chargeableWeight, 2) }}</td>
          <td>{{ $deliveredEvent ? \Carbon\Carbon::parse($deliveredEvent->created_at)->format('d/m/Y') : '—' }}</td>
          <td>{{ $deliveredEvent ? \Carbon\Carbon::parse($deliveredEvent->created_at)->format('H:i') : '—' }}</td>
          <td>{{ $deliveredEvent?->received_by ?? '—' }}</td>
          <td>{{ $s->consignee_city ?? '—' }}</td>
          <td>{{ $s->consignee_name  ?? '-' }}</td>
          <td style="text-align:right;">{{ number_format($rowAmount, 2) }}</td>
        </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="11" style="text-align:right;">Total</td>
        <td style="text-align:right;">{{ number_format($shipmentTotal, 2) }}</td>
      </tr>
    </tfoot>
  </table>

  <div class="page-num">Page 1 of 2</div>

</div>{{-- end invoice-box --}}
</div>{{-- end page --}}

{{-- ════════════════════════════════════════════
     PAGE 2 — Charge summary + totals
     ════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div class="page">
<div class="invoice-box">

  {{-- Page 2 header bar — pure table, no floats ── --}}
  <div style="display:table;width:100%;border-bottom:1px solid #ccc;">
    <div style="display:table-cell;padding:5px 12px;font-weight:bold;font-size:10px;">
      Invoice No. {{ $vkInvoice->invoice_number }}
    </div>
    <div style="display:table-cell;padding:5px 12px;font-size:10px;text-align:center;">
      Period: {{ \Carbon\Carbon::parse($vkInvoice->from_date)->format('d/m/Y') }}
      – {{ \Carbon\Carbon::parse($vkInvoice->to_date)->format('d/m/Y') }}
    </div>
    <div style="display:table-cell;padding:5px 12px;font-size:10px;text-align:right;">
      Page 2 of 2
    </div>
  </div>

  {{-- Charge breakdown (left) | Signatory (right) — table layout, no floats ── --}}
  <div class="totals-section">
    <div style="display:table;width:100%;">

      {{-- Left cell: charge totals ── --}}
      <div style="display:table-cell;width:60%;vertical-align:top;padding-right:24px;">
        <table class="totals-table">
          <tr>
            <td class="t-label">Freight + VAS</td>
            <td class="t-value">{{ number_format($vkInvoice->freight_vas, 2) }}</td>
          </tr>
          <tr>
            <td class="t-label">Fuel Surcharge</td>
            <td class="t-value">{{ number_format($vkInvoice->fuel_surcharge, 2) }}</td>
          </tr>
          @if($vkInvoice->fod_dod > 0)
          <tr>
            <td class="t-label">FOD / DOD</td>
            <td class="t-value">{{ number_format($vkInvoice->fod_dod, 2) }}</td>
          </tr>
          @endif
          <tr class="t-divider">
            <td class="t-label"><strong>Total</strong></td>
            <td class="t-value"><strong>{{ number_format($vkInvoice->subtotal, 2) }}</strong></td>
          </tr>
          <tr>
            <td class="t-label">Net Amount</td>
            <td class="t-value">{{ number_format($vkInvoice->subtotal, 2) }}</td>
          </tr>
          @if($isIntraState)
            <tr>
              <td class="t-label">CGST @ 9% on ₹{{ number_format($vkInvoice->subtotal, 2) }}</td>
              <td class="t-value">{{ number_format($vkInvoice->cgst, 2) }}</td>
            </tr>
            <tr>
              <td class="t-label">SGST @ 9% on ₹{{ number_format($vkInvoice->subtotal, 2) }}</td>
              <td class="t-value">{{ number_format($vkInvoice->sgst, 2) }}</td>
            </tr>
          
            <tr>
              <td class="t-label">IGST @ 18% on ₹{{ number_format($vkInvoice->subtotal, 2) }}</td>
              <td class="t-value">{{ number_format($vkInvoice->igst, 2) }}</td>
            </tr>
          @endif
          <tr class="t-grand">
            <td class="t-label">Grand Total</td>
            <td class="t-value">{{ number_format($vkInvoice->grand_total, 2) }}</td>
          </tr>
        </table>
      </div>

      {{-- Right cell: signatory ── --}}
      <div style="display:table-cell;width:40%;vertical-align:top;text-align:center;padding-top:6px;">
        <div style="font-size:10px;font-weight:bold;margin-bottom:52px;">
          For VK Enterprises
        </div>
        <div style="border-top:1px solid #333;padding-top:4px;font-size:9px;color:#444;">
          Authorised Signatory
        </div>
      </div>

    </div>{{-- end two-col table --}}

    {{-- Amount in words — full width below ── --}}
    <div class="amount-words-line" style="margin-top:12px;">
      {{ $amountInWords }}
    </div>

  </div>{{-- end totals-section --}}

  {{-- Terms & Conditions --}}
  <div class="tnc-section">
    <div class="tnc-heading">Terms and Conditions</div>
    <div class="tnc-row">
      <div class="tnc-num">1</div>
      <div class="tnc-text">Interest @ 2% per month will be charged on delayed payments.</div>
    </div>
    <div class="tnc-row">
      <div class="tnc-num">2</div>
      <div class="tnc-text">All invoices to be paid within the credit period specified as per the contractual terms.</div>
    </div>
    <div class="tnc-row">
      <div class="tnc-num">3</div>
      <div class="tnc-text">All surcharges are applicable on AWB Fee &amp; FOV charges.</div>
    </div>
  </div>

  {{-- Footer --}}
  <div class="footer-bar">
    @if($vkInvoice->branch)
      Registered Office: VK Enterprises, {{ $vkInvoice->branch->name }}, {{ $vkInvoice->branch->address }}
      @if($vkInvoice->branch->phone) &nbsp;|&nbsp; Tel: {{ $vkInvoice->branch->phone }} @endif
      @if($vkInvoice->branch->email) &nbsp;|&nbsp; Email: {{ $vkInvoice->branch->email }} @endif
    @endif
  </div>

</div>{{-- end invoice-box --}}
</div>{{-- end page --}}

</body>
</html>