<?php

namespace Tests\Unit;

use App\Support\CollabTableRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollabTableRendererTest extends TestCase
{
    #[Test]
    public function it_merges_a_repeated_regional_group_row(): void
    {
        $html = CollabTableRenderer::dataRowsHtml([
            array_fill(0, 5, 'Regional 1 - Ahmad Fauzi'),
        ]);

        $this->assertSame(
            '<tr class="collab-group-row"><td class="collab-sticky-label-cell"><span>Regional 1 - Ahmad Fauzi</span></td><td colspan="4" aria-hidden="true"></td></tr>',
            $html
        );
        $this->assertSame(1, substr_count($html, 'Regional 1 - Ahmad Fauzi'));
    }

    #[Test]
    public function it_does_not_merge_an_ordinary_data_row(): void
    {
        $html = CollabTableRenderer::dataRowsHtml([
            ['1', 'SG.0001.2024 - Staff', 'Regional 1 - Ahmad Fauzi'],
        ]);

        $this->assertStringNotContainsString('collab-group-row', $html);
        $this->assertSame(3, substr_count($html, '<td'));
        $this->assertStringContainsString('>Staff</td>', $html);
        $this->assertStringNotContainsString('>SG.0001.2024 - Staff</td>', $html);
        $this->assertStringContainsString('title="SG.0001.2024 - Staff"', $html);
    }

    #[Test]
    public function it_keeps_a_subtotal_label_available_for_sticky_positioning(): void
    {
        $html = CollabTableRenderer::dataRowsHtml([
            ['Total Regional 1', 'Total Regional 1', '8', '12'],
        ]);

        $this->assertStringContainsString(
            '<td class="collab-sticky-label-cell"><span>Total Regional 1</span></td><td colspan="1" aria-hidden="true"></td>',
            $html
        );
    }
}
