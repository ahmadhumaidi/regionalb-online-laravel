<?php

namespace App\Services\Dashboard;

/**
 * Ports rsm_normalize_closing_status() (rsm_db.php:4009): free-text
 * classification of whatever raw closing_status string a staff member
 * typed, into one of a small set of buckets. Order matters — negatives are
 * checked first so e.g. "belum closing" doesn't get classified as Closing.
 */
class ClosingStatusClassifier
{
    public static function normalize(string $raw): string
    {
        $value = mb_strtolower(trim($raw));

        if ($value === '') {
            return '';
        }

        foreach (['belum', 'tidak', 'gagal', 'batal', 'cancel', 'non', 'no closing'] as $negative) {
            if (str_contains($value, $negative)) {
                return 'Belum closing';
            }
        }

        if (str_contains($value, 'potensi')) {
            return 'Potensi closing';
        }

        if (str_contains($value, 'her') || str_contains($value, 'daftar ulang')) {
            return 'Herregistrasi';
        }

        if (str_contains($value, 'registrasi') || str_contains($value, 'daftar')
            || str_contains($value, 'closing') || str_contains($value, 'close')) {
            return 'Closing';
        }

        return ucfirst(trim($raw));
    }

    /**
     * @param iterable<string> $rawStatuses
     * @return array{registrasi: list<string>, herreg: list<string>}
     */
    public static function buckets(iterable $rawStatuses): array
    {
        $registrasi = [];
        $herreg = [];

        foreach ($rawStatuses as $raw) {
            $normalized = self::normalize((string) $raw);
            $key = mb_strtolower(trim((string) $raw));

            if (in_array($normalized, ['Closing', 'Herregistrasi'], true)) {
                $registrasi[] = $key;
            }
            if ($normalized === 'Herregistrasi') {
                $herreg[] = $key;
            }
        }

        return ['registrasi' => $registrasi, 'herreg' => $herreg];
    }
}
