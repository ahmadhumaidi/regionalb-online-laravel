<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Generic "Laporan & Rekap" Excel export for the marketing/other/all rekap
 * types (the "ads" type keeps its own richer, regional-grouped
 * AdsRekapExport). Same columns as the CSV export, matching legacy's
 * export_rekap_xlsx()/rekap_export_rows() which uses one flat table for
 * every $type.
 */
class RekapExport implements FromArray, WithEvents
{
    private const HEADER_ROW = 5;

    private int $lastRow = self::HEADER_ROW;

    public function __construct(
        private readonly string $area,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly string $type,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        $sheet = [
            ['Area', $this->area, '', '', '', '', '', '', ''],
            ['Periode', $this->dateFrom, $this->dateTo, '', '', '', '', '', ''],
            ['Jenis Rekap', $this->type, '', '', '', '', '', '', ''],
            array_fill(0, 9, ''),
            ['Tanggal', 'Jenis', 'Wilayah', 'Unit/Kampus', 'Staff', 'Judul/Campaign', 'Leads', 'Closing', 'Anggaran', 'Realisasi', 'Status'],
        ];

        foreach ($this->rows as $row) {
            $sheet[] = [
                $row['report_date'],
                $row['report_type'],
                $row['wilayah'] ?: '-',
                $row['unit_name'] ?: '-',
                $row['staff_name'] ?: '-',
                $row['title'] ?: '-',
                $row['leads_count'],
                $row['closing_count'],
                $row['budget_requested'],
                $row['realization_amount'],
                $row['status'] ?: '-',
            ];
        }

        $this->lastRow = count($sheet);

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'K';

                $sheet->getStyle('A1:A3')->getFont()->setBold(true);
                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastCol.self::HEADER_ROW)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9CA3AF']]],
                ]);

                if ($this->lastRow > self::HEADER_ROW) {
                    $sheet->getStyle('I'.(self::HEADER_ROW + 1).':J'.$this->lastRow)->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    $sheet->getStyle('G'.(self::HEADER_ROW + 1).':H'.$this->lastRow)->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastCol.$this->lastRow)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                    ]);
                }

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth($col === 'F' ? 32 : 16);
                }
                $sheet->freezePane('A'.(self::HEADER_ROW + 1));
            },
        ];
    }
}
