<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Report Breakdown</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { margin: 0 0 10px; font-size: 20px; text-align: center; }
        p { margin: 0 0 3px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
        .meta { margin-top: 8px; font-size: 10px; color: #6b7280; }
        .summary { margin-top: 6px; font-size: 10px; font-weight: bold; color: #111827; }
        .signatures { width: 48%; margin-left: auto; margin-top: 52px; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: bottom; border: none; padding: 0 8px; }
        .signature-space { height: 54px; }
        .signature-label { font-size: 10px; color: #111827; }
    </style>
</head>
<body>
    <h1>Report Breakdown Maintenance</h1>
    <div class="meta">
        Periode Breakdown: {{ $filters['start_date'] ?? '-' }} s/d {{ $filters['end_date'] ?? '-' }}<br>
        Dicetak: {{ now()->format('Y-m-d H:i:s') }} | Oleh: {{ $printedBy ?? '-' }}<br>
    </div>
    <div class="summary">
        Summary: Case OPEN {{ $summary['open'] ?? 0 }}, Case CLOSED {{ $summary['closed'] ?? 0 }}, Total Downtime {{ $summary['downtime_hours'] ?? 0 }} Jam
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Breakdown</th>
                <th>Tanggal Breakdown</th>
                <th>Kode Mesin</th>
                <th>Nama Mesin</th>
                <th>Problem</th>
                <th>Root Cause</th>
                <th>Action Taken</th>
                <th>Countermeasure</th>
                <th>Tanggal Selesai</th>
                <th>PIC Open</th>
                <th>PIC Closed</th>
                <th>Status</th>
                <th>Downtime</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row->breakdown_code }}</td>
                    <td>{{ $reportBreakdownService->dateTimeOrDash($row->breakdown_at) }}</td>
                    <td>{{ $row->machine?->machine_code ?? '-' }}</td>
                    <td>{{ $row->machine_name_snapshot ?? '-' }}</td>
                    <td>{{ $row->problem ?? '-' }}</td>
                    <td>{{ $row->root_cause ?? '-' }}</td>
                    <td>{{ $row->action_taken ?? '-' }}</td>
                    <td>{{ $row->countermeasure ?? '-' }}</td>
                    <td>{{ $reportBreakdownService->dateTimeOrDash($row->closed_at) }}</td>
                    <td>{{ $row->created_by_name_snapshot ?? '-' }}</td>
                    <td>{{ $row->closed_by_name_snapshot ?? '-' }}</td>
                    <td>{{ $reportBreakdownService->statusLabel($row) }}</td>
                    <td>{{ $reportBreakdownService->downtimeHourLabel($row) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
            <td class="signature-space"></td>
        </tr>
        <tr>
            <td class="signature-label">Dibuat</td>
            <td class="signature-label">Dicek</td>
            <td class="signature-label">Disetujui</td>
        </tr>
    </table>
</body>
</html>
