<?php

namespace Tests\Unit;

use App\Services\CollabSourceService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CollabSourceServiceTest extends TestCase
{
    #[Test]
    public function it_assigns_nugroho_to_regional_seven_in_the_banner_report(): void
    {
        $normalize = new ReflectionMethod(CollabSourceService::class, 'normalizeReportTables');
        $tables = [[
            array_fill(0, 4, 'Regional - - Nugroho Budi Santoso'),
            ['1', 'SG.0944.2026 - Nugroho Budi Santoso', '-', '-'],
            array_fill(0, 4, 'Total Regional - - Nugroho Budi Santoso'),
        ]];

        $result = $normalize->invoke(null, 'Pasang Spanduk', $tables);

        $this->assertSame('Regional 7 - Nugroho Budi Santoso', $result[0][0][0]);
        $this->assertSame('Regional 7 - Nugroho Budi Santoso', $result[0][0][3]);
        $this->assertSame('Total Regional 7 - Nugroho Budi Santoso', $result[0][2][0]);
    }
}
