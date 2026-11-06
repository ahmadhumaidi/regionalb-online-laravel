<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ScoringExport extends StringValueBinder implements FromArray, WithCustomValueBinder, WithEvents
{
    public function __construct(
        private readonly string $area,
        private readonly array $filters,
        private readonly array $table,
        private readonly array $indicators,
    ) {
        $this->setNumericConversion(false);
    }

    public function array(): array
    {
        $headers = ['Staff', 'Wilayah', 'Unit/Kampus'];
        foreach ($this->indicators as $indicator) {
            $headers[] = $indicator['label'];
        }

        $sheet = [
            ['Laporan Aktivitas'],
            ['Area', $this->area],
            ['Periode', $this->filters['date_from'], $this->filters['date_to']],
            ['Filter Wilayah', $this->filters['wilayah'] ?: 'Semua dalam akses pengguna', 'Unit/Kampus', $this->filters['unit_name'] ?: 'Semua', 'Staff', $this->filters['staff_name'] ?: 'Semua'],
            ['Sumber Collab tersinkron', $this->table['synced_at'] ?? '-', 'Diunduh', now('Asia/Jakarta')->format('Y-m-d H:i:s').' WIB'],
            $headers,
        ];

        foreach ($this->table['rows'] as $row) {
            $values = [$row['name'], $row['wilayah'], $row['unit_name']];
            foreach ($this->indicators as $indicator) {
                $values[] = (float) ($row[$indicator['metric_key']] ?? 0);
            }
            $sheet[] = $values;
        }

        return $sheet;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = $sheet->getHighestColumn();
            $sheet->freezePane('D7');
            $sheet->setAutoFilter('A6:'.$lastColumn.$sheet->getHighestRow());
            $sheet->getStyle('A6:'.$lastColumn.'6')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                'alignment' => ['wrapText' => true],
            ]);
            $sheet->getDefaultColumnDimension()->setWidth(18);
            foreach (['A', 'B', 'C'] as $column) {
                $sheet->getColumnDimension($column)->setWidth(28);
            }
        }];
    }
}
