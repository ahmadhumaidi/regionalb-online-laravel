<?php

namespace App\Support;

/**
 * Reconciles campus/unit name spelling between rsm_users.campus_name and the
 * external Collab source's campus_name column (they don't always agree —
 * e.g. rsm_users has "Universitas Patria Artha ( UPA )" where Collab has
 * plain "Universitas Patria Artha"). Extracted out of AchievementReportService
 * so any other caller needing to scope Collab rows down to one campus (e.g.
 * CollabMetricsService::campusTotals() for a staff user) can reuse the same
 * matching rules instead of drifting out of sync with a second copy.
 */
class CampusMatcher
{
    /**
     * Campus labels that should collapse into one unit even though they
     * don't share a substring/parenthetical relation, keyed by lowercased
     * source label, valued by the display label of the target they merge
     * into:
     *  - "same real campus, spelled differently" (found by diffing every
     *    distinct rsm_users.campus_name against every distinct "Closing
     *    Kampus Regional" campus_name, 2026-08-10)
     *  - "different campuses administratively reported together" (one staff
     *    member covers both — confirmed by user, 2026-08-10: Ahmad Wirda
     *    Burhanudin Mubarroq handles both IKIP and STIE Widya Darma)
     */
    private const ALIASES = [
        'sekolah tinggi bahasa asing lia yogyakarta (stba lia)' => 'STBA Lia Yogyakarta',
        'institut teknologi dan bisnis stikom bali' => 'ITB STIKOM Bali',
        'ikip widya darma' => 'IKIP Widya Darma / STIE Widya Darma',
        'stie widya darma' => 'IKIP Widya Darma / STIE Widya Darma',
    ];

    /** A label's own form, translated through ALIASES if it's a merge source. */
    public static function canonicalLabel(string $label): string
    {
        return self::ALIASES[mb_strtolower(trim($label))] ?? trim($label);
    }

    public static function matches(string $a, string $b): bool
    {
        $keysA = self::matchKeys($a);
        $keysB = self::matchKeys($b);

        return $keysA !== [] && $keysB !== [] && array_intersect($keysA, $keysB) !== [];
    }

    /**
     * Candidate match keys for a campus/unit label: the label itself, its
     * "plain" form with parenthetical content stripped, any parenthetical
     * content alone — covers "Full Name (ABBR)" vs "ABBR"-only naming — and,
     * for a combined "A / B" display label (two real campuses merged into
     * one card via ALIASES), each side alone, so a raw unit label matching
     * just "A" still finds the merged row.
     *
     * @return list<string>
     */
    public static function matchKeys(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $keys = [mb_strtolower($label)];

        $plain = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/\s*[\(\[].*?[\)\]]\s*/', ' ', $label)));
        if ($plain !== '' && ! in_array(mb_strtolower($plain), $keys, true)) {
            $keys[] = mb_strtolower($plain);
        }

        if (preg_match_all('/[\(\[]([^\)\]]+)[\)\]]/', $label, $matches)) {
            foreach ($matches[1] as $paren) {
                $parenKey = mb_strtolower(trim($paren));
                if ($parenKey !== '' && ! in_array($parenKey, $keys, true)) {
                    $keys[] = $parenKey;
                }
            }
        }

        if (str_contains($label, '/')) {
            foreach (explode('/', $label) as $segment) {
                $segmentKey = mb_strtolower(trim($segment));
                if ($segmentKey !== '' && ! in_array($segmentKey, $keys, true)) {
                    $keys[] = $segmentKey;
                }
            }
        }

        if (isset(self::ALIASES[$keys[0]])) {
            $aliasKey = mb_strtolower(self::ALIASES[$keys[0]]);
            if (! in_array($aliasKey, $keys, true)) {
                $keys[] = $aliasKey;
            }
        }

        return $keys;
    }
}
