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
      font-size: 9.5px;
      color: #1a1a1a;
      background: #fff;
    }

    .page { padding: 16px 20px; }
    .page-break { page-break-after: always; }

    /* ── Header: logo | bill-to | invoice meta ── */
    .header-row {
      display: table;
      width: 100%;
      border-bottom: 2px solid #1d4ed8;
      padding-bottom: 8px;
      margin-bottom: 8px;
    }
    .hdr-cell {
      display: table-cell;
      vertical-align: top;
    }
    .hdr-logo {
      width: 26%;
      padding-right: 10px;
    }
    .hdr-logo img { max-width: 100px; max-height: 52px; margin-bottom: 4px; }
    .hdr-company-name { font-size: 10px; font-weight: bold; color: #1a1a1a; }
    .hdr-addr { font-size: 8.5px; color: #444; line-height: 1.5; margin-top: 2px; }
    .hdr-gstin { font-size: 8.5px; margin-top: 3px; }
    .hdr-gstin strong { color: #1d4ed8; }

    .hdr-billto {
      width: 42%;
      padding: 0 10px;
      border-left: 1px solid #ddd;
      border-right: 1px solid #ddd;
    }
    .bill-label { font-size: 8px; font-weight: bold; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
    .bill-name { font-size: 12px; font-weight: bold; color: #1d4ed8; margin-top: 2px; }
    .bill-addr { font-size: 8.5px; color: #333; line-height: 1.55; margin-top: 3px; }
    .bill-gstin { font-size: 8.5px; margin-top: 4px; font-weight: bold; }

    .hdr-meta { width: 32%; padding-left: 10px; text-align: right; }
    .meta-invoice-no { font-size: 13px; font-weight: bold; color: #1d4ed8; letter-spacing: 0.5px; }
    .meta-invoice-label { font-size: 7.5px; color: #888; text-transform: uppercase; }
    .meta-row { font-size: 8.5px; margin-top: 3px; color: #333; }
    .meta-row strong { color: #1a1a1a; }

    /* ── Compliance detail strip ── */
    table.detail-strip {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 10px;
      font-size: 8.5px;
    }
    table.detail-strip td {
      border: 1px solid #ddd;
      padding: 3px 6px;
      background: #f8fafc;
    }
    table.detail-strip td.k { font-weight: bold; color: #555; width: 15%; }
    table.detail-strip td.v { width: 18.3%; }

    /* ── Shipment table ── */
    .section-heading {
      font-size: 9.5px;
      font-weight: bold;
      color: #fff;
      background: #1d4ed8;
      padding: 4px 8px;
      margin-bottom: 4px;
    }

    table.shipments { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.shipments thead th {
      border-bottom: 1.5px solid #1d4ed8;
      border-top: 1px solid #1d4ed8;
      padding: 4px 4px;
      text-align: left;
      font-weight: bold;
      color: #1d4ed8;
      background: #eef2ff;
    }
    table.shipments td {
      border-bottom: 1px solid #eee;
      padding: 3px 4px;
      vertical-align: middle;
    }
    table.shipments tfoot td {
      font-weight: bold;
      border-top: 1.5px solid #1d4ed8;
      background: #eef2ff;
      padding: 5px 4px;
    }

    .page-num { text-align: right; font-size: 8px; color: #888; margin-top: 6px; }

    /* ── Page 2 ── */
    .p2-header {
      display: table;
      width: 100%;
      border-bottom: 2px solid #1d4ed8;
      padding-bottom: 6px;
      margin-bottom: 12px;
    }
    .p2-header .hdr-cell { font-size: 9px; }
    .p2-header .hdr-logo img { max-width: 90px; max-height: 44px; }

    .p2-body { display: table; width: 100%; }
    .p2-left { display: table-cell; width: 56%; vertical-align: top; padding-right: 18px; }
    .p2-right { display: table-cell; width: 44%; vertical-align: top; }

    .words-label { font-size: 9px; font-weight: bold; color: #1d4ed8; text-transform: uppercase; }
    .words-value { font-size: 11px; font-weight: bold; margin-top: 3px; text-transform: capitalize; }

    .terms-label { font-size: 9px; font-weight: bold; color: #1d4ed8; text-transform: uppercase; margin-top: 16px; }
    .terms-list { margin-top: 5px; }
    .term-item { font-size: 8.5px; color: #333; line-height: 1.7; }
    .term-item .n { display: inline-block; width: 12px; font-weight: bold; }
    .terms-extra { font-size: 8.5px; color: #333; line-height: 1.7; margin-top: 4px; }

    table.totals-table { width: 100%; border-collapse: collapse; }
    table.totals-table td { padding: 4px 6px; font-size: 9px; border-bottom: 1px solid #eee; }
    table.totals-table .t-label { color: #444; }
    table.totals-table .t-pct { color: #888; font-size: 8px; padding-left: 3px; }
    table.totals-table .t-value { text-align: right; font-weight: bold; }
    table.totals-table .t-sub td { border-top: 1.5px solid #1d4ed8; font-weight: bold; }
    table.totals-table .t-net td {
      border-top: 2px solid #1d4ed8;
      border-bottom: 2px solid #1d4ed8;
      font-size: 11px;
      font-weight: bold;
      color: #1d4ed8;
      padding: 6px;
    }

    .signature-block { margin-top: 40px; text-align: right; }
    .signature-for { font-size: 9.5px; font-weight: bold; color: #c2410c; }
    .signature-line { margin-top: 42px; border-top: 1px solid #333; display: inline-block; padding-top: 4px; font-size: 8.5px; color: #c2410c; font-weight: bold; }

    .footer-bar {
      margin-top: 18px;
      border-top: 1px solid #ddd;
      padding-top: 6px;
      text-align: center;
      font-size: 7.5px;
      color: #777;
      line-height: 1.6;
    }
  </style>
</head>
<body>

{{-- ════════════════════════════════════════════
     PAGE 1 — Header + Shipment list
     ════════════════════════════════════════════ --}}
<div class="page">

  {{-- Header: our letterhead | bill-to customer | invoice meta --}}
  <div class="header-row">
    <div class="hdr-cell hdr-logo">
      @if($logoPath)
        <img src="{{ $logoPath }}" alt="{{ $company->company_name }}" />
      @endif
      <div class="hdr-company-name">{{ $company->company_name }}</div>
      <div class="hdr-addr">
        @if($vkInvoice->branch)
          {{ $vkInvoice->branch->name }}<br/>
          {{ implode(', ', array_filter([
              $vkInvoice->branch->address_line_1,
              $vkInvoice->branch->address_line_2,
              $vkInvoice->branch->address_line_3,
          ])) }}
          @if($vkInvoice->branch->phone)<br/>Tel: {{ $vkInvoice->branch->phone }}@endif
        @else
          {{ $company->company_address }}
        @endif
      </div>
      @if($vkGstin)
        <div class="hdr-gstin"><strong>GSTIN:</strong> {{ $vkGstin }}</div>
      @endif
    </div>

    <div class="hdr-cell hdr-billto">
      <div class="bill-label">Bill To</div>
      <div class="bill-name">{{ $vkInvoice->customer?->company_name ?? '—' }}</div>
      @php
        $billingAddr = $vkInvoice->customer?->addresses?->firstWhere('address_type', 'billing')
                    ?? $vkInvoice->customer?->addresses?->first();
      @endphp
      @if($billingAddr)
        <div class="bill-addr">
          {{ implode(', ', array_filter([
              $billingAddr->address_line1,
              $billingAddr->address_line2,
              $billingAddr->city,
              $billingAddr->pincode,
          ])) }}
        </div>
      @endif
      @if($billingAddr?->gst_number)
        <div class="bill-gstin">GSTIN: {{ $billingAddr->gst_number }}</div>
      @endif
    </div>

    <div class="hdr-cell hdr-meta">
      <div class="meta-invoice-label">Invoice No</div>
      <div class="meta-invoice-no">{{ $vkInvoice->invoice_number }}</div>
      <div class="meta-row"><strong>Period:</strong>
        {{ \Carbon\Carbon::parse($vkInvoice->from_date)->format('d-M-Y') }}
        to {{ \Carbon\Carbon::parse($vkInvoice->to_date)->format('d-M-Y') }}
      </div>
      <div class="meta-row"><strong>Invoice Date:</strong> {{ \Carbon\Carbon::parse($vkInvoice->created_at)->format('d-M-Y') }}</div>
      <div class="meta-row"><strong>Page:</strong> 1 of 2</div>
    </div>
  </div>

  {{-- Compliance detail strip --}}
  <table class="detail-strip">
    <tr>
      <td class="k">Customer A/C</td><td class="v">{{ $vkInvoice->customer?->customer_code ?? '—' }}</td>
      <td class="k">SAC Code</td><td class="v">996812</td>
      <td class="k">State Code</td>
    </tr>
    <tr>
      <td class="k">Description</td><td class="v">Courier Services</td>
      <td class="k">Place of Supply</td><td class="v">{{ $placeOfSupply ?: '—' }}</td>
      <td class="v">{{ $branchGstCode ?? '—' }}</td>
    </tr>
  </table>

  {{-- Shipment Details heading --}}
  <div class="section-heading">Shipment Details</div>

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

          $pickupManifest = $s->manifests->first();
          $deliveredEvent = $s->events->first();

          $serviceCode = strtoupper(substr($s->service_type ?? $s->service ?? '', 0, 1));
        @endphp
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $serviceCode ?: '—' }}</td>
          <td>{{ $s->awb_number }}</td>
          <td>{{ $pickupManifest ? \Carbon\Carbon::parse($pickupManifest->created_at)->format('d-M-Y') : '—' }}</td>
          <td style="text-align:right;">{{ $pieces }}</td>
          <td style="text-align:right;">{{ number_format($chargeableWeight, 2) }}</td>
          <td>{{ $deliveredEvent ? \Carbon\Carbon::parse($deliveredEvent->created_at)->format('d-M-Y') : '—' }}</td>
          <td>{{ $deliveredEvent ? \Carbon\Carbon::parse($deliveredEvent->created_at)->format('H:i') : '—' }}</td>
          <td>{{ $deliveredEvent?->received_by ?? '—' }}</td>
          <td>{{ $s->consignee_city ?? '—' }}</td>
          <td>{{ $s->consignee_name  ?? '—' }}</td>
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

</div>{{-- end page --}}

{{-- ════════════════════════════════════════════
     PAGE 2 — Charge summary + totals
     ════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div class="page">

  <div class="p2-header">
    <div class="hdr-cell hdr-logo">
      @if($logoPath)
        <img src="{{ $logoPath }}" alt="{{ $company->company_name }}" />
      @endif
    </div>
    <div class="hdr-cell" style="width:44%;">
      <strong>Invoice No.</strong> {{ $vkInvoice->invoice_number }}
    </div>
    <div class="hdr-cell" style="width:30%; text-align:right;">
      Page: 2 of 2
    </div>
  </div>

  <div class="p2-body">

    {{-- Left: amount in words + terms --}}
    <div class="p2-left">
      <div class="words-label">Amount In Words</div>
      <div class="words-value">{{ $amountInWords }}</div>

      <div class="terms-label">Terms &amp; Conditions</div>
      <div class="terms-list">
        <div class="term-item"><span class="n">1.</span>Payment should be made by A/C Payee Cheque
          @if($company->bank_account_name) / NEFT @endif
          in favour of {{ $company->bank_account_name ?: $company->company_name }}.</div>
        <div class="term-item"><span class="n">2.</span>All complaints regarding this invoice must be raised within 8 days of receipt.</div>
        <div class="term-item"><span class="n">3.</span>2% interest per month will be charged on invoices not paid within 15 days.</div>
        @if($vkInvoice->fuel_surcharge > 0)
          <div class="term-item"><span class="n">4.</span>Fuel surcharge is applicable as per prevailing rates for the invoice period.</div>
        @endif
      </div>

      <div class="terms-extra">
        @if($vkGstin)GST Number: {{ $vkGstin }}<br/>@endif
        SAC Code: 996812
        @if($company->bank_account_number)
          <br/>Bank: {{ $company->bank_name }}, A/C No: {{ $company->bank_account_number }}, IFSC: {{ $company->bank_ifsc_code }}
        @endif
      </div>

      @if($company->terms_conditions)
        <div class="terms-extra" style="margin-top:8px;">{{ $company->terms_conditions }}</div>
      @endif
    </div>

    {{-- Right: charge summary --}}
    <div class="p2-right">
      <table class="totals-table">
        <tr>
          <td class="t-label">Total Amount</td>
          <td class="t-value">{{ number_format($vkInvoice->subtotal, 2) }}</td>
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
        <tr>
          <td class="t-label">Currency Adj Factor</td>
          <td class="t-value">0.00</td>
        </tr>
        <tr>
          <td class="t-label">IDC</td>
          <td class="t-value">0.00</td>
        </tr>
        <tr>
          <td class="t-label">CGST <span class="t-pct">@if($vkInvoice->cgst > 0)9%@endif</span></td>
          <td class="t-value">{{ number_format($vkInvoice->cgst, 2) }}</td>
        </tr>
        <tr>
          <td class="t-label">SGST <span class="t-pct">@if($vkInvoice->sgst > 0)9%@endif</span></td>
          <td class="t-value">{{ number_format($vkInvoice->sgst, 2) }}</td>
        </tr>
        <tr>
          <td class="t-label">IGST <span class="t-pct">@if($vkInvoice->igst > 0)18%@endif</span></td>
          <td class="t-value">{{ number_format($vkInvoice->igst, 2) }}</td>
        </tr>
        <tr>
          <td class="t-label">Discount</td>
          <td class="t-value">0.00</td>
        </tr>
        <tr class="t-net">
          <td class="t-label">Net Amount</td>
          <td class="t-value">{{ number_format($vkInvoice->grand_total, 2) }}</td>
        </tr>
      </table>

      <div class="signature-block">
        <div class="signature-for">For {{ $company->company_name }}</div>
        <div class="signature-line">Authorised Signatory</div>
      </div>
    </div>

  </div>{{-- end p2-body --}}

  <div class="footer-bar">
    @if($vkInvoice->branch)
      Registered Office: {{ $company->company_name }}, {{ $vkInvoice->branch->name }},
      {{ implode(', ', array_filter([
          $vkInvoice->branch->address_line_1,
          $vkInvoice->branch->address_line_2,
          $vkInvoice->branch->address_line_3,
      ])) }}
      @if($vkInvoice->branch->phone) &nbsp;|&nbsp; Tel: {{ $vkInvoice->branch->phone }} @endif
      @if($vkInvoice->branch->email) &nbsp;|&nbsp; Email: {{ $vkInvoice->branch->email }} @endif
    @else
      {{ $company->company_address }}
    @endif
  </div>

</div>{{-- end page --}}

</body>
</html>
