<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/**
 * Port of download-ad-lead-template.php — same headers/example rows, plus
 * the Status Closing dropdown data validation on rows 2-500.
 */
class AdLeadTemplateExport implements FromArray, WithEvents, WithHeadings
{
    private const CLOSING_OPTIONS = ['Belum closing', 'Potensi closing', 'Closing', 'Herregistrasi', 'Tidak closing'];

    public function headings(): array
    {
        return [
            'Nama Lead', 'No HP WA', 'Email', 'Kampus', 'Jurusan', 'Kota Asal',
            'Hasil Follow Up', 'Status Progress', 'Status Closing', 'Update Closing', 'Catatan Kendala Progress',
        ];
    }

    public function array(): array
    {
        return [
            ['Contoh Nama Lead', '081234567890', 'lead@example.com', 'Contoh Kampus', 'Manajemen', 'Surabaya', 'Sudah dihubungi via WA', 'Prospek', 'Closing', 'Potensi daftar minggu ini', 'Minta info biaya'],
            ['Contoh Lead 2', '082222222222', 'lead2@example.com', 'Contoh Kampus 2', 'Akuntansi', 'Malang', 'Belum respon', 'Follow up ulang', 'Belum closing', 'Belum ada closing', 'Hubungi kembali sore'],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $validation = new DataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowErrorMessage(true);
                $validation->setFormula1('"'.implode(',', self::CLOSING_OPTIONS).'"');

                for ($row = 2; $row <= 500; $row++) {
                    $event->sheet->getDelegate()->setDataValidation('I'.$row, clone $validation);
                }
            },
        ];
    }
}
