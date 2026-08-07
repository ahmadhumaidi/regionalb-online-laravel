<?php

namespace App\Exports;

use App\Services\Reports\ReportRecapService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "Rekap laporan iklan" Excel export. Grouped Regional-only (no per-kampus
 * subtotal — kampus still shows as its own column on each data row, just
 * without a dedicated subtotal row) with a Grand Total row at the bottom,
 * per user request. Visual styling (navy Regional rows, money formatting,
 * merged section labels) ports the design of the never-reachable
 * export_ads_excel() (dashboard.php:2837-2932); the actually-reachable
 * export_rows_xlsx() writes plain unstyled cells, which is what "belum
 * rapi" was pointing at.
 */
class AdsRekapExport implements FromArray, WithEvents
{
    private const HEADER_ROW = 5;

    private const MONEY_COLS = ['G', 'H', 'K'];

    private const COUNT_COLS = ['I', 'J'];

    private const COL_COUNT = 12;

    /** @var list<int> */
    private array $regionalRows = [];

    private ?int $grandTotalRow = null;

    private int $lastRow = self::HEADER_ROW;

    public function __construct(
        private readonly string $area,
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        $blank = array_fill(0, self::COL_COUNT, '');

        $sheet = [
            array_pad(['Area', $this->area], self::COL_COUNT, ''),
            array_pad(['Periode', $this->dateFrom, $this->dateTo], self::COL_COUNT, ''),
            array_pad(['Jenis Rekap', 'Rekap laporan iklan'], self::COL_COUNT, ''),
            $blank,
            ['Tanggal', 'Periode Iklan', 'Regional', 'Unit/Kampus', 'Platform', 'Campaign', 'Anggaran', 'Realisasi', 'Leads', 'Closing', 'CPL', 'Status'],
        ];

        foreach (ReportRecapService::groupAdsRows($this->rows, $this->area) as $regionalGroup) {
            $regionalTotals = $regionalGroup['totals'];
            $sheet[] = [
                'REGIONAL: '.$regionalGroup['wilayah'].' — '.$regionalTotals['count'].' laporan', '', '', '', '', '',
                $regionalTotals['budget_requested'], $regionalTotals['realization_amount'],
                $regionalTotals['leads_count'], $regionalTotals['closing_count'], $regionalTotals['cpl'], '',
            ];
            $this->regionalRows[] = count($sheet);

            foreach ($regionalGroup['campuses'] as $campusGroup) {
                foreach ($campusGroup['rows'] as $row) {
                    $sheet[] = [
                        $row['report_date'],
                        $row['ad_period'] ?: '-',
                        $row['wilayah'] ?: '-',
                        $row['unit_name'] ?: '-',
                        $row['platform'] ?: '-',
                        $row['campaign_name'] ?: $row['title'] ?: '-',
                        $row['budget_requested'],
                        $row['realization_amount'],
                        $row['leads_count'],
                        $row['closing_count'],
                        $row['cpl'],
                        $row['status'] ?: '-',
                    ];
                }
            }
        }

        $grandTotal = ReportRecapService::adsGrandTotal($this->rows);
        $sheet[] = [
            'GRAND TOTAL — '.$grandTotal['count'].' laporan', '', '', '', '', '',
            $grandTotal['budget_requested'], $grandTotal['realization_amount'],
            $grandTotal['leads_count'], $grandTotal['closing_count'], $grandTotal['cpl'], '',
        ];
        $this->grandTotalRow = count($sheet);

        $this->lastRow = count($sheet);

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'L';

                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth($col === 'A' ? 42 : ($col === 'F' ? 30 : 16));
                }

                // Meta rows (Area/Periode/Jenis Rekap)
                $sheet->getStyle('A1:A3')->getFont()->setBold(true);

                // Column header row
                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastCol.self::HEADER_ROW)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9CA3AF']]],
                ]);

                foreach ($this->regionalRows as $row) {
                    $sheet->mergeCells('A'.$row.':F'.$row);
                    $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2F3D8F']],
                    ]);
                }

                if ($this->grandTotalRow !== null) {
                    $row = $this->grandTotalRow;
                    $sheet->mergeCells('A'.$row.':F'.$row);
                    $sheet->getStyle('A'.$row.':'.$lastCol.$row)->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '111827']],
                        'borders' => ['top' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']]],
                    ]);
                }

                if ($this->lastRow >= self::HEADER_ROW) {
                    foreach (self::MONEY_COLS as $col) {
                        $sheet->getStyle($col.(self::HEADER_ROW + 1).':'.$col.$this->lastRow)->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    }
                    foreach (self::COUNT_COLS as $col) {
                        $sheet->getStyle($col.(self::HEADER_ROW + 1).':'.$col.$this->lastRow)->getNumberFormat()->setFormatCode('#,##0');
                    }
                    $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastCol.$this->lastRow)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
                    ]);
                }

                $sheet->getStyle('A'.self::HEADER_ROW.':'.$lastCol.$this->lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->freezePane('A'.(self::HEADER_ROW + 1));
            },
        ];
    }
}
