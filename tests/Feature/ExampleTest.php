<?php

namespace Tests\Feature;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    #[DataProvider('protectedPagesProvider')]
    public function test_guest_is_redirected_from_migrated_pages(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    /** @return array<string, array{string}> */
    public static function protectedPagesProvider(): array
    {
        return [
            'anggaran' => ['/anggaran'],
            'pencapaian' => ['/pencapaian'],
            'konten' => ['/konten'],
            'kegiatan' => ['/kegiatan'],
            'aktivitas' => ['/aktivitas'],
        ];
    }
}
