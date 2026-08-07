<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Raw row reader for "Data Hasil Iklan" uploads — no heading-row formatter,
 * so row 0 stays the header labels exactly like legacy's
 * rsm_parse_ad_lead_file() output, which AdLeadImportService::mapAdLeadRows()
 * expects (header row + data rows, matched by normalized header text).
 */
class AdLeadsImport implements ToArray
{
    public function array(array $array): array
    {
        return $array;
    }
}
