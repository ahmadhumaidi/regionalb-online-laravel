<?php

namespace App\Support;

/**
 * Ports render_collab_header_rows/render_collab_data_row/collab_header_runs
 * from public_html/dashboard.php so the raw Collab table on staging merges
 * header cells (rowspan/colspan) and highlights subtotal rows like production,
 * instead of dumping every row as plain <td> cells.
 */
class CollabTableRenderer
{
    public static function headerRowsHtml(array $headerRows): string
    {
        $headerRows = array_values($headerRows);
        $rowCount = count($headerRows);
        if ($rowCount === 0) {
            return '';
        }

        if ($rowCount !== 2) {
            $html = '';
            foreach ($headerRows as $row) {
                $html .= '<tr>';
                foreach (self::headerRuns(array_values((array) $row)) as $runIndex => $run) {
                    $colspanAttr = $run['width'] > 1 ? ' colspan="'.$run['width'].'"' : '';
                    $classAttr = $runIndex === 0 ? ' class="sticky-col"' : '';
                    $html .= '<th'.$colspanAttr.$classAttr.'>'.e($run['value']).'</th>';
                }
                $html .= '</tr>';
            }

            return $html;
        }

        $row0 = array_values((array) $headerRows[0]);
        $row1 = array_values((array) $headerRows[1]);
        $skipCols = [];
        $topCells = [];
        foreach (self::headerRuns($row0) as $run) {
            $sameBelow = true;
            for ($c = $run['start']; $c < $run['start'] + $run['width']; $c++) {
                if ((string) ($row1[$c] ?? '') !== $run['value']) {
                    $sameBelow = false;
                    break;
                }
            }
            $topCells[] = ['value' => $run['value'], 'colspan' => $run['width'], 'rowspan' => $sameBelow ? 2 : 1];
            if ($sameBelow) {
                for ($c = $run['start']; $c < $run['start'] + $run['width']; $c++) {
                    $skipCols[$c] = true;
                }
            }
        }

        $html = '<tr>';
        foreach ($topCells as $cellIndex => $cell) {
            $attrs = '';
            if ($cell['colspan'] > 1) {
                $attrs .= ' colspan="'.$cell['colspan'].'"';
            }
            if ($cell['rowspan'] > 1) {
                $attrs .= ' rowspan="'.$cell['rowspan'].'"';
            }
            if ($cellIndex === 0) {
                $attrs .= ' class="sticky-col"';
            }
            $html .= '<th'.$attrs.'>'.e($cell['value']).'</th>';
        }
        $html .= '</tr>';

        $bottomValues = [];
        foreach ($row1 as $idx => $value) {
            if (! isset($skipCols[$idx])) {
                $bottomValues[] = $value;
            }
        }
        $bottomStartsAtColumnZero = ! isset($skipCols[0]);
        if ($bottomValues !== []) {
            $html .= '<tr>';
            foreach (self::headerRuns($bottomValues) as $runIndex => $run) {
                $colspanAttr = $run['width'] > 1 ? ' colspan="'.$run['width'].'"' : '';
                $classAttr = ($runIndex === 0 && $bottomStartsAtColumnZero) ? ' class="sticky-col"' : '';
                $html .= '<th'.$colspanAttr.$classAttr.'>'.e($run['value']).'</th>';
            }
            $html .= '</tr>';
        }

        return $html;
    }

    public static function dataRowsHtml(array $rows): string
    {
        $html = '';
        foreach ($rows as $row) {
            $html .= self::dataRowHtml((array) $row);
        }

        return $html;
    }

    private static function dataRowHtml(array $row): string
    {
        $row = array_values($row);
        $firstCell = trim((string) ($row[0] ?? ''));
        $isSubtotalRow = $firstCell !== '' && stripos($firstCell, 'Total ') === 0;

        $nonEmptyValues = array_values(array_filter(
            array_map(static fn (mixed $cell): string => trim((string) $cell), $row),
            static fn (string $cell): bool => $cell !== ''
        ));
        $isRegionalGroupRow = $firstCell !== ''
            && preg_match('/^Regional\b/i', $firstCell) === 1
            && count(array_unique($nonEmptyValues)) === 1;

        if ($isRegionalGroupRow) {
            $remainingWidth = count($row) - 1;

            return '<tr class="collab-group-row"><td class="collab-sticky-label-cell"><span>'.e($firstCell).'</span></td>'
                .($remainingWidth > 0 ? '<td colspan="'.$remainingWidth.'" aria-hidden="true"></td>' : '')
                .'</tr>';
        }

        if (! $isSubtotalRow) {
            $html = '<tr>';
            foreach ($row as $cell) {
                $cellText = (string) $cell;
                $isNumericCell = $cellText !== '' && preg_match('/^-?\d+([.,]\d+)?$/', trim($cellText)) === 1;
                $displayText = self::withoutNikPrefix($cellText);
                $titleAttr = $displayText !== $cellText ? ' title="'.e($cellText).'"' : '';
                $html .= '<td class="'.($isNumericCell ? 'num' : '').'"'.$titleAttr.'>'.e($displayText).'</td>';
            }

            return $html.'</tr>';
        }

        $labelWidth = 1;
        while ($labelWidth < count($row) && trim((string) $row[$labelWidth]) === $firstCell) {
            $labelWidth++;
        }

        $html = '<tr class="collab-subtotal-row">';
        $html .= '<td class="collab-sticky-label-cell"><span>'.e($firstCell).'</span></td>';
        if ($labelWidth > 1) {
            $html .= '<td colspan="'.($labelWidth - 1).'" aria-hidden="true"></td>';
        }
        foreach (array_slice($row, $labelWidth) as $cell) {
            $cellText = (string) $cell;
            $isNumericCell = $cellText !== '' && preg_match('/^-?\d+([.,]\d+)?$/', trim($cellText)) === 1;
            $html .= '<td class="'.($isNumericCell ? 'num' : '').'">'.e($cellText).'</td>';
        }

        return $html.'</tr>';
    }

    private static function withoutNikPrefix(string $value): string
    {
        $trimmed = trim($value);
        $name = preg_replace('/^[A-Z]{1,10}\.\d{2,8}\.\d{4}\s*-\s*/iu', '', $trimmed);

        return is_string($name) && $name !== '' ? $name : $value;
    }

    private static function headerRuns(array $row): array
    {
        $runs = [];
        $start = 0;
        $count = count($row);
        for ($i = 1; $i <= $count; $i++) {
            if ($i === $count || ($row[$i] ?? null) !== $row[$start]) {
                $runs[] = ['start' => $start, 'width' => $i - $start, 'value' => (string) $row[$start]];
                $start = $i;
            }
        }

        return $runs;
    }
}
