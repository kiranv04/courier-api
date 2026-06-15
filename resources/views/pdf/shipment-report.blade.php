<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Shipment Report</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
    .page { padding: 28px 32px; }
    .title { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
    .subtitle { font-size: 10px; color: #555; margin-bottom: 16px; }
    .filters { font-size: 10px; color: #444; margin-bottom: 16px; }
    .filters span { margin-right: 16px; }
    table { width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 20px; }
    th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px; color: #374151; border: 1px solid #e5e7eb; }
    td { padding: 6px 8px; border: 1px solid #e5e7eb; }
    tr:nth-child(even) td { background: #f9fafb; }
    .total-row td { font-weight: bold; background: #f0fdf4 !important; }
    .section-title { font-size: 13px; font-weight: bold; margin: 16px 0 8px; }
  </style>
</head>
<body>
<div class="page">
  <div class="title">Shipment Report</div>
  <div class="subtitle">Generated on {{ now()->format('d M Y, h:i A') }}</div>

  <div class="filters">
    @if($filters['branch_id']) <span>Branch ID: {{ $filters['branch_id'] }}</span> @endif
    @if($filters['customer_type']) <span>Customer Type: {{ ucfirst($filters['customer_type']) }}</span> @endif
    @if($filters['service_type']) <span>Service Type: {{ $filters['service_type'] }}</span> @endif
    @if($filters['date_from']) <span>From: {{ $filters['date_from'] }}</span> @endif
    @if($filters['date_to']) <span>To: {{ $filters['date_to'] }}</span> @endif
  </div>

  <div class="section-title">Status Breakdown</div>
  <table>
    <thead>
      <tr><th>Status</th><th>Count</th></tr>
    </thead>
    <tbody>
      @foreach($report['by_status'] as $status => $count)
        <tr>
          <td>{{ ucwords(str_replace('_', ' ', $status)) }}</td>
          <td>{{ $count }}</td>
        </tr>
      @endforeach
      <tr class="total-row">
        <td>Total</td>
        <td>{{ $report['total'] }}</td>
      </tr>
    </tbody>
  </table>

  @if($report['by_branch'])
  <div class="section-title">Branch Breakdown</div>
  <table>
    <thead>
      <tr>
        <th>Branch</th>
        @foreach(\array_keys($report['by_branch'][0]['by_status']) as $status)
          <th>{{ ucwords(str_replace('_', ' ', $status)) }}</th>
        @endforeach
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      @foreach($report['by_branch'] as $branch)
        <tr>
          <td>{{ $branch['branch_name'] }} ({{ $branch['branch_code'] }})</td>
          @foreach($branch['by_status'] as $count)
            <td>{{ $count }}</td>
          @endforeach
          <td>{{ $branch['total'] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
</body>
</html>