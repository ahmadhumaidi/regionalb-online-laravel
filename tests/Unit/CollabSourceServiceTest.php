<?php

namespace Tests\Unit;

use App\Services\CollabSourceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CollabSourceServiceTest extends TestCase
{
    #[Test]
    public function it_assigns_nugroho_to_regional_seven_in_every_collab_report(): void
    {
        $normalize = new ReflectionMethod(CollabSourceService::class, 'normalizeReportTables');
        $tables = [[
            array_fill(0, 4, 'Regional - - Nugroho Budi Santoso'),
            ['1', 'SG.0944.2026 - Nugroho Budi Santoso', '-', '-'],
            array_fill(0, 4, 'Total Regional - - Nugroho Budi Santoso'),
        ]];

        foreach (['Pasang Spanduk', 'Sebar Brosur', 'Canvasing', 'Follow Up BDC'] as $reportName) {
            $result = $normalize->invoke(null, $reportName, $tables);

            $this->assertSame('Regional 7 - Nugroho Budi Santoso', $result[0][0][0], $reportName);
            $this->assertSame('Regional 7 - Nugroho Budi Santoso', $result[0][0][3], $reportName);
            $this->assertSame('Total Regional 7 - Nugroho Budi Santoso', $result[0][2][0], $reportName);
        }
    }

    #[Test]
    public function it_normalizes_the_single_dash_regional_seven_header_used_by_follow_up_bdc(): void
    {
        $normalize = new ReflectionMethod(CollabSourceService::class, 'normalizeReportTables');
        $tables = [[
            array_fill(0, 4, 'Regional - Nugroho Budi Santoso'),
            ['SG.0822.2024 - Ari Oktavian Aji', '0', '30', '31'],
            array_fill(0, 4, 'Total Regional - Nugroho Budi Santoso'),
        ]];

        $result = $normalize->invoke(null, 'Follow Up BDC', $tables);

        $this->assertSame('Regional 7 - Nugroho Budi Santoso', $result[0][0][0]);
        $this->assertSame('Total Regional 7 - Nugroho Budi Santoso', $result[0][2][0]);
    }
}
