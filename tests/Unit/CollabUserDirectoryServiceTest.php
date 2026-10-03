<?php

namespace Tests\Unit;

use App\Services\CollabUserDirectoryService;
use PHPUnit\Framework\TestCase;

class CollabUserDirectoryServiceTest extends TestCase
{
    public function test_it_extracts_only_regional_a_staff_and_their_campuses(): void
    {
        $snapshot = ['reports' => [
            'Closing Personal Per Regional' => ['tables' => [[
                ['Nama', '01', 'Total'],
                ['Regional 1 - Korwil', 'Regional 1 - Korwil'],
                ['SG.0001.2026 - Koordinator (Korwil Regional 1)', '0', '0'],
                ['SG.0002.2026 - Staff Satu Tim Terpilih', '0', '0', '08123456789', '1 Tahun, 0 Bulan'],
                ['Regional 4 - Korwil', 'Regional 4 - Korwil'],
                ['SG.0004.2026 - Staff Luar', '0', '0'],
            ]]],
            'Sebar Brosur' => ['tables' => [[
                ['No', 'NIK - Nama', 'Kode', 'Kampus'],
                ['Regional 1 - Korwil', 'Regional 1 - Korwil'],
                ['1', 'SG.0002.2026 - Staff Satu', 'abc', 'Kampus Satu'],
                ['2', 'SG.0002.2026 - Staff Satu', 'xyz', 'Kampus Dua'],
                ['Regional 4 - Korwil', 'Regional 4 - Korwil'],
                ['3', 'SG.0004.2026 - Staff Luar', 'out', 'Kampus Luar'],
            ]]],
        ]];

        $directory = CollabUserDirectoryService::directoryFromSnapshot($snapshot);

        $this->assertCount(1, $directory['staff']);
        $this->assertCount(1, $directory['coordinators']);
        $this->assertSame('Koordinator', $directory['coordinators']['SG00012026']['name']);
        $this->assertSame('Regional 1', $directory['coordinators']['SG00012026']['regional']);
        $this->assertSame('Staff Satu', $directory['staff']['SG00022026']['name']);
        $this->assertSame('Regional 1', $directory['staff']['SG00022026']['regional']);
        $this->assertSame(['abc', 'xyz'], $directory['staff_campuses']['SG00022026']);
        $this->assertArrayNotHasKey('out', $directory['campuses']);
    }
}
