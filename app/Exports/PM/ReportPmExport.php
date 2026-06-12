<?php

namespace App\Exports\PM;

use App\Services\Report\ReportPmService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportPmExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, object>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ReportPmService $reportPmService,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Tanggal PM',
            'Lokasi',
            'Kode Mesin',
            'Nama Mesin',
            'Part Mesin',
            'Standard Pengecekan',
            'Tipe Input',
            'Hasil Action',
            'Hasil Angka / Range',
            'Satuan',
            'Warning',
            'Catatan',
            'PIC Operator',
            'Status PM',
            'Approved By',
            'Tanggal Submit',
            'Tanggal Approve',
        ];
    }

    /**
     * @param  object  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        return [
            $this->reportPmService->dateTimeOrDash($row->scheduled_date, 'Y-m-d'),
            (string) ($row->location_name ?? '-'),
            (string) ($row->machine_code ?? '-'),
            (string) ($row->machine_name ?? '-'),
            (string) ($row->part_name_snapshot ?? '-'),
            (string) ($row->standard_name_snapshot ?? '-'),
            $row->input_type_snapshot ? strtoupper((string) $row->input_type_snapshot) : '-',
            $this->reportPmService->resultAction($row),
            $this->reportPmService->resultValue($row),
            (string) ($row->unit_snapshot ?: '-'),
            $this->reportPmService->warningLabel($row),
            (string) ($row->operator_note ?: '-'),
            (string) ($row->operator_name_snapshot ?? '-'),
            $this->reportPmService->statusLabel($row),
            (string) ($row->approved_by_name_snapshot ?? '-'),
            $this->reportPmService->dateTimeOrDash($row->submitted_at),
            $this->reportPmService->dateTimeOrDash($row->approved_at),
        ];
    }
}
