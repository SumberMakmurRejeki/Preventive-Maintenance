<?php

namespace App\Exports\Breakdown;

use App\Models\Breakdown;
use App\Services\Report\ReportBreakdownService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ReportBreakdownExport implements FromCollection, WithHeadings, WithMapping
{
    protected int $rowNumber = 0;

    /**
     * @param  Collection<int, Breakdown>  $rows
     */
    public function __construct(
        protected Collection $rows,
        protected ReportBreakdownService $reportBreakdownService,
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
            'No',
            'Kode Breakdown',
            'Tanggal Breakdown',
            'Kode Mesin',
            'Nama Mesin',
            'Problem',
            'Root Cause',
            'Action Taken',
            'Countermeasure',
            'Tanggal Selesai',
            'PIC Open',
            'PIC Closed',
            'Status',
            'Downtime',
        ];
    }

    /**
     * @param  Breakdown  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        return [
            (string) ++$this->rowNumber,
            (string) $row->breakdown_code,
            $this->reportBreakdownService->dateTimeOrDash($row->breakdown_at),
            (string) ($row->machine?->machine_code ?? '-'),
            (string) ($row->machine_name_snapshot ?? '-'),
            (string) ($row->problem ?? '-'),
            (string) ($row->root_cause ?? '-'),
            (string) ($row->action_taken ?? '-'),
            (string) ($row->countermeasure ?? '-'),
            $this->reportBreakdownService->dateTimeOrDash($row->closed_at),
            (string) ($row->created_by_name_snapshot ?? '-'),
            (string) ($row->closed_by_name_snapshot ?? '-'),
            $this->reportBreakdownService->statusLabel($row),
            $this->reportBreakdownService->downtimeHourLabel($row),
        ];
    }
}
