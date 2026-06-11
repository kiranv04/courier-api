<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tax Invoice – {{ $shipment->awb_number }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 11px;
      color: #111;
      background: #fff;
    }

    .page {
      padding: 20px 24px;
    }

    /* ── Outer border ── */
    .invoice-box {
      border: 1.5px solid #333;
      width: 100%;
    }

    /* ── Header: company | Tax Invoice | AWB ── */
    .header-row {
      display: table;
      width: 100%;
      border-bottom: 1.5px solid #333;
    }
    .header-company {
      display: table-cell;
      width: 38%;
      padding: 10px 14px;
      vertical-align: middle;
    }
    .header-title {
      display: table-cell;
      width: 24%;
      text-align: center;
      vertical-align: middle;
      padding: 10px 4px;
      border-left: 1px solid #ccc;
      border-right: 1px solid #ccc;
    }
    .header-awb {
      display: table-cell;
      width: 38%;
      vertical-align: middle;
      padding: 10px 14px;
    }

    .company-name {
      font-size: 18px;
      font-weight: bold;
      letter-spacing: 0.4px;
    }
    .company-sub {
      font-size: 9px;
      color: #555;
      margin-top: 2px;
    }
    .tax-invoice-heading {
      font-size: 15px;
      font-weight: bold;
    }
    .awb-label {
      font-size: 9px;
      color: #555;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .awb-value {
      font-size: 13px;
      font-weight: bold;
      font-family: 'Courier New', monospace;
      letter-spacing: 1px;
      margin-top: 2px;
    }
    .original-note {
      font-size: 8px;
      color: #555;
      margin-top: 3px;
      font-style: italic;
    }

    /* ── Body: two panels ── */
    .body-row {
      display: table;
      width: 100%;
    }
    .panel-left {
      display: table-cell;
      width: 50%;
      vertical-align: top;
      border-right: 1.5px solid #333;
    }
    .panel-right {
      display: table-cell;
      width: 50%;
      vertical-align: top;
    }

    /* ── Address blocks ── */
    .addr-block {
      padding: 10px 14px;
      border-bottom: 1px solid #ccc;
    }
    .addr-block:last-child {
      border-bottom: none;
    }
    .addr-section-label {
      font-size: 9px;
      font-weight: bold;
      color: #444;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-bottom: 5px;
    }
    .addr-name {
      font-size: 11px;
      font-weight: bold;
      margin-bottom: 3px;
    }
    .addr-line {
      font-size: 10px;
      color: #333;
      line-height: 1.6;
    }
    .gst-line {
      font-size: 10px;
      margin-top: 5px;
      color: #222;
    }
    .gst-line strong {
      font-weight: bold;
    }

    /* ── Detail rows (right panel) ── */
    .detail-row {
      display: table;
      width: 100%;
      border-bottom: 1px solid #e0e0e0;
    }
    .detail-row:last-child {
      border-bottom: none;
    }
    .detail-key {
      display: table-cell;
      width: 50%;
      padding: 5px 12px;
      font-size: 10px;
      color: #444;
      vertical-align: middle;
      border-right: 1px solid #e8e8e8;
    }
    .detail-val {
      display: table-cell;
      width: 50%;
      padding: 5px 12px;
      font-size: 10px;
      font-weight: bold;
      color: #111;
      vertical-align: middle;
    }
    .detail-row.grand-total .detail-key,
    .detail-row.grand-total .detail-val {
      font-size: 11px;
      font-weight: bold;
      background: #f0f0f0;
      border-top: 1.5px solid #333;
    }

    /* ── Amount in words ── */
    .amount-words {
      border-top: 1px solid #ccc;
      padding: 6px 12px;
      font-size: 9.5px;
      color: #111;
      font-style: italic;
    }

    /* ── Stamp & Sign ── */
    .stamp-box {
      border-top: 1px solid #ccc;
      padding: 8px 12px;
      min-height: 72px;
    }
    .stamp-label {
      font-size: 10px;
      color: #444;
    }
    .strike-note {
      font-size: 8px;
      color: #666;
      font-style: italic;
      margin-top: 48px;
    }

    /* ── Footer ── */
    .footer-box {
      border-top: 1.5px solid #333;
      padding: 7px 14px;
      text-align: center;
      font-size: 8.5px;
      color: #555;
      line-height: 1.7;
    }
  </style>
</head>
<body>
<div class="page">
<div class="invoice-box">

  {{-- ── HEADER ── --}}
  <div class="header-row">

    <div class="header-company">
      <div class="company-name">VK Enterprises</div>
      <div class="company-sub">Courier &amp; Logistics Services</div>
    </div>

    <div class="header-title">
      <div class="tax-invoice-heading">Tax Invoice</div>
    </div>

    <div class="header-awb">
      <div class="awb-label">AWB No.</div>
      <div class="awb-value">{{ $shipment->awb_number }}</div>
      <div class="original-note">*Original copy for the recipient</div>
    </div>

  </div>{{-- end header-row --}}

  {{-- ── BODY ── --}}
  <div class="body-row">

    {{-- LEFT: Billing (VK branch) + Customer (shipper) ── --}}
    <div class="panel-left">

      {{-- VK Billing Address --}}
      <div class="addr-block">
        <div class="addr-section-label">Billing Address</div>
        <div class="addr-name">VK Enterprises</div>
        @if($shipment->branch)
          <div class="addr-line">
            {{ $shipment->branch->name }}<br/>
            {!! nl2br(e($shipment->branch->address)) !!}
            @if($shipment->branch->phone)
              <br/>Tel: {{ $shipment->branch->phone }}
            @endif
          </div>
        @endif
        @if($vkGstin)
          <div class="gst-line"><strong>GSTIN No:</strong> {{ $vkGstin }}</div>
        @endif
      </div>

      {{-- Customer (Shipper) Address --}}
      <div class="addr-block">
        <div class="addr-section-label">Customer Name &amp; Address</div>
        <div class="addr-name">{{ $shipment->shipper_name }}</div>
        @if($shipment->shipper_company_name)
          <div class="addr-line">{{ $shipment->shipper_company_name }}</div>
        @endif
        <div class="addr-line">
          {{ implode(', ', array_filter([
              $shipment->shipper_address_line1,
              $shipment->shipper_address_line2,
              $shipment->shipper_city,
              $shipment->shipper_state,
              $shipment->shipper_pincode,
          ])) }}
        </div>
        @if($shipment->shipper_phone)
          <div class="addr-line" style="margin-top:3px;">Tel: {{ $shipment->shipper_phone }}</div>
        @endif
        @if($shipment->shipper_gst)
          <div class="gst-line"><strong>GSTIN No:</strong> {{ $shipment->shipper_gst }}</div>
        @endif
        @if($shipment->shipper_email)
          <div class="gst-line"><strong>Email:</strong> {{ $shipment->shipper_email }}</div>
        @endif
      </div>

    </div>{{-- end panel-left --}}

    {{-- RIGHT: Invoice detail rows ── --}}
    <div class="panel-right">

      <div class="detail-row">
        <div class="detail-key">Invoice No.</div>
        <div class="detail-val">: {{ $invoice?->invoice_number ?? '—' }}</div>
      </div>

      <div class="detail-row">
        <div class="detail-key">Invoice Date</div>
        <div class="detail-val">
          : {{ $invoice?->created_at
              ? \Carbon\Carbon::parse($invoice->created_at)->format('d/m/Y')
              : now()->format('d/m/Y') }}
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-key">Description of Service</div>
        <div class="detail-val">: Courier Service</div>
      </div>

      <div class="detail-row">
        <div class="detail-key">HSN / SAC No.</div>
        <div class="detail-val">: 996812</div>
      </div>

      <div class="detail-row">
        <div class="detail-key">State Code</div>
        <div class="detail-val">: {{ $consigneeGstCode ?? '—' }}</div>
      </div>

      <div class="detail-row">
        <div class="detail-key">Place of Supply</div>
        <div class="detail-val">: {{ $placeOfSupply ?? '—' }}</div>
      </div>

      <div class="detail-row">
        <div class="detail-key">RCM Applicable</div>
        <div class="detail-val">: N</div>
      </div>

      {{-- Taxable amount = pre-GST total --}}
      <div class="detail-row">
        <div class="detail-key">Taxable Amount</div>
        <div class="detail-val">: ₹{{ number_format($shipment->charges?->total ?? 0, 2) }}</div>
      </div>

      @if($isIntraState)
        <div class="detail-row">
          <div class="detail-key">CGST @ 9%</div>
          <div class="detail-val">: ₹{{ number_format($cgst, 2) }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-key">SGST / UGST @ 9%</div>
          <div class="detail-val">: ₹{{ number_format($sgst, 2) }}</div>
        </div>
        <div class="detail-row">
          <div class="detail-key">IGST @ 18%</div>
          <div class="detail-val">: 0.00</div>
        </div>
      @else
        <div class="detail-row">
          <div class="detail-key">CGST @ 9%</div>
          <div class="detail-val">: 0.00</div>
        </div>
        <div class="detail-row">
          <div class="detail-key">SGST / UGST @ 9%</div>
          <div class="detail-val">: 0.00</div>
        </div>
        <div class="detail-row">
          <div class="detail-key">IGST @ 18%</div>
          <div class="detail-val">: ₹{{ number_format($igst, 2) }}</div>
        </div>
      @endif

      {{-- K F Cess — commented until required --}}
      {{--
      <div class="detail-row">
        <div class="detail-key">K F Cess @ 1%</div>
        <div class="detail-val">: 0.00</div>
      </div>
      --}}

      <div class="detail-row grand-total">
        <div class="detail-key">Grand Total</div>
        <div class="detail-val">: ₹{{ number_format($grandTotal, 2) }}</div>
      </div>

      {{-- Amount in words --}}
      <div class="amount-words">
        {{ $amountInWords }}
      </div>

      {{-- Stamp & Sign --}}
      <div class="stamp-box">
        <div class="stamp-label">Stamp &amp; Sign :</div>
        <div class="strike-note">*Strike whichever is not applicable.</div>
      </div>

    </div>{{-- end panel-right --}}

  </div>{{-- end body-row --}}

  {{-- ── FOOTER ── --}}
  <div class="footer-box">
    @if($shipment->branch)
      Registered Office: VK Enterprises,
      {{ $shipment->branch->name }},
      {{ $shipment->branch->address }}
      @if($shipment->branch->phone) &nbsp;|&nbsp; Tel: {{ $shipment->branch->phone }} @endif
      @if($shipment->branch->email) &nbsp;|&nbsp; Email: {{ $shipment->branch->email }} @endif
    @endif
  </div>

</div>{{-- end invoice-box --}}
</div>{{-- end page --}}
</body>
</html>