<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Report PM</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { margin: 0 0 6px; font-size: 18px; }
        p { margin: 0 0 3px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        .meta { margin-top: 8px; font-size: 10px; color: #6b7280; }
        .summary { margin-top: 8px; font-size: 10px; color: #374151; }
        .catatan-col { min-width: 100px; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
        .date-col { min-width: 74px; white-space: nowrap; font-size: 9px; }
        .signatures { width: 48%; margin-left: auto; margin-top: 52px; border-collapse: collapse; }
        .signatures td { width: 33.33%; text-align: center; vertical-align: bottom; border: none; padding: 0 8px; }
        .signature-space { height: 54px; }
        .signature-label { font-size: 10px; color: #111827; }
    </style>
</head>
<body>
    <h1>Report PM</h1>
    <p>Pantau histori preventive maintenance, hasil pengecekan, status PM, dan warning mesin.</p>
    <div class="meta">
        Periode: {{ $filters['start_date'] ?? '-' }} s/d {{ $filters['end_date'] ?? '-' }} |
        Waktu export: {{ now()->format('Y-m-d H:i:s') }} |
        Admin: {{ $printedBy ?? '-' }}
    </div>
    <div class="summary">
        Total data: {{ $summary['total'] ?? 0 }} |
        Warning: {{ $summary['warning'] ?? 0 }} |
        Scheduled: {{ $summary['scheduled'] ?? 0 }} |
        In Progress: {{ $summary['in_progress'] ?? 0 }} |
        Waiting Review: {{ $summary['waiting_review'] ?? 0 }} |
        Approved: {{ $summary['approved'] ?? 0 }} |
        Overdue: {{ $summary['overdue'] ?? 0 }} |
        Missed: {{ $summary['missed'] ?? 0 }}
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Tgl PM</th>
                <th>Lokasi</th>
                <th>Kode</th>
                <th>Mesin</th>
                <th>Part</th>
                <th>Standard</th>
                <th>Tipe</th>
                <th>Action</th>
                <th>Nilai</th>
                <th>Satuan</th>
                <th>Warning</th>
                <th class="catatan-col">Catatan</th>
                <th>Operator</th>
                <th>Status</th>
                <th>Approve</th>
                <th class="date-col">Tgl Submit</th>
                <th class="date-col">Tgl Approve</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $reportPmService->dateTimeOrDash($row->scheduled_date, 'Y-m-d') }}</td>
                    <td>{{ $row->location_name ?? '-' }}</td>
                    <td>{{ $row->machine_code ?? '-' }}</td>
                    <td>{{ $row->machine_name ?? '-' }}</td>
                    <td>{{ $row->part_name_snapshot ?? '-' }}</td>
                    <td>{{ $row->standard_name_snapshot ?? '-' }}</td>
                    <td>{{ $row->input_type_snapshot ? strtoupper($row->input_type_snapshot) : '-' }}</td>
                    <td>{{ $reportPmService->resultAction($row) }}</td>
                    <td>{{ $reportPmService->resultValue($row) }}</td>
                    <td>{{ $row->unit_snapshot ?: '-' }}</td>
                    <td>{{ $reportPmService->warningLabel($row) }}</td>
                    <td class="catatan-col">{{ $row->operator_note ?: '-' }}</td>
                    <td>{{ $row->operator_name_snapshot ?? '-' }}</td>
                    <td>{{ $reportPmService->statusLabel($row) }}</td>
                    <td>{{ $row->approved_by_name_snapshot ?? '-' }}</td>
                    <td class="date-col">{{ $reportPmService->dateTimeOrDash($row->submitted_at, 'Y-m-d H:i') }}</td>
                    <td class="date-col">{{ $reportPmService->dateTimeOrDash($row->approved_at, 'Y-m-d H:i') }}</td>
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
