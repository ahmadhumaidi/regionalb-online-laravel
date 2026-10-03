<?php

namespace App\Services;

use App\Models\RsmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CollabUserDirectoryService
{
    /**
     * Reconcile the Regional A staff directory and campus ownership from the
     * same Collab snapshot used by the dashboard.
     *
     * @return array{staff:int,coordinators:int,inserted:int,updated:int,deactivated:int,campuses:int}
     */
    public static function sync(array $snapshot): array
    {
        $directory = self::directoryFromSnapshot($snapshot);
        if ($directory['staff'] === []) {
            return ['staff' => 0, 'coordinators' => 0, 'inserted' => 0, 'updated' => 0, 'deactivated' => 0, 'campuses' => 0];
        }

        return DB::transaction(function () use ($directory): array {
            $campusLabels = self::syncCampuses($directory['campuses']);
            $seenIds = [];
            $inserted = 0;
            $updated = 0;

            foreach ($directory['coordinators'] as $coordinator) {
                $username = self::username($coordinator['nik'], $coordinator['name']);
                if (DB::table('rsm_deleted_usernames')->where('username', $username)->exists()) {
                    continue;
                }

                $user = RsmUser::query()->where('username', $username)->first()
                    ?? RsmUser::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($coordinator['name'])])->first();
                $attributes = [
                    'name' => $coordinator['name'],
                    'nik' => $coordinator['nik'],
                    'role' => RsmUser::ROLE_KOORDINATOR,
                    'jabatan' => 'Koordinator Wilayah',
                    'regional' => $coordinator['regional'],
                    'area' => 'Regional A',
                    'phone_number' => $coordinator['phone_number'] ?: $user?->phone_number,
                    'work_duration' => $coordinator['work_duration'] ?: $user?->work_duration,
                    'is_active' => true,
                ];

                if ($user) {
                    $user->update($attributes);
                    $updated++;
                } else {
                    RsmUser::create($attributes + [
                        'username' => $username,
                        'password_hash' => Hash::make('kptsukses'),
                        'must_change_password' => true,
                    ]);
                    $inserted++;
                }
            }

            foreach ($directory['staff'] as $staff) {
                $username = self::username($staff['nik'], $staff['name']);
                if (DB::table('rsm_deleted_usernames')->where('username', $username)->exists()) {
                    continue;
                }

                $user = RsmUser::query()->where('username', $username)->first()
                    ?? RsmUser::query()
                        ->where('role', RsmUser::ROLE_STAFF)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($staff['name'])])
                        ->first();

                $assignedCodes = $directory['staff_campuses'][$staff['nik_key']] ?? [];
                $assignedLabels = array_values(array_filter(array_map(
                    static fn (string $code): ?string => $campusLabels[$code] ?? null,
                    $assignedCodes,
                )));
                $campusName = self::preferredCampus((string) ($user?->campus_name ?? ''), $assignedLabels);

                $attributes = [
                    'name' => $staff['name'],
                    'nik' => $staff['nik'],
                    'role' => RsmUser::ROLE_STAFF,
                    'jabatan' => 'Staff Unit',
                    'regional' => $staff['regional'],
                    'area' => 'Regional A',
                    'campus_name' => $campusName,
                    'phone_number' => $staff['phone_number'] ?: $user?->phone_number,
                    'work_duration' => $staff['work_duration'] ?: $user?->work_duration,
                    'is_active' => true,
                ];

                if ($user) {
                    $user->update($attributes);
                    $updated++;
                } else {
                    $user = RsmUser::create($attributes + [
                        'username' => $username,
                        'password_hash' => Hash::make('kptsukses'),
                        'must_change_password' => true,
                    ]);
                    $inserted++;
                }
                $seenIds[] = $user->id;
            }

            $deactivated = RsmUser::query()
                ->where('role', RsmUser::ROLE_STAFF)
                ->where(function ($query): void {
                    $query->where('area', 'Regional A')
                        ->orWhereIn('regional', ['Regional 1', 'Regional 2', 'Regional 3']);
                })
                ->whereNotIn('id', $seenIds ?: [0])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return [
                'staff' => count($seenIds),
                'coordinators' => count($directory['coordinators']),
                'inserted' => $inserted,
                'updated' => $updated,
                'deactivated' => $deactivated,
                'campuses' => count($campusLabels),
            ];
        });
    }

    /** @return array{staff:array<string,array>,coordinators:array<string,array>,campuses:array<string,array>,staff_campuses:array<string,list<string>>} */
    public static function directoryFromSnapshot(array $snapshot): array
    {
        $reports = (array) ($snapshot['reports'] ?? []);
        $staff = [];
        $coordinators = [];
        $staffRows = (array) ($reports['Closing Personal Per Regional']['tables'][0] ?? []);
        $currentRegional = null;

        foreach ($staffRows as $row) {
            $label = trim((string) ($row[0] ?? ''));
            if (preg_match('/^Regional\s+([1-7])\s*-/i', $label, $match)) {
                $currentRegional = in_array((int) $match[1], [1, 2, 3], true) ? 'Regional '.$match[1] : null;
                continue;
            }
            if ($currentRegional !== null && preg_match('/^(SG[.\d-]+)\s*-\s*(.+?)\s*\(Korwil\s+Regional\s+[1-3]\)$/i', $label, $match)) {
                $nik = trim($match[1]);
                $nikKey = self::nikKey($nik);
                $coordinators[$nikKey] = [
                    'nik' => $nik,
                    'name' => trim($match[2]),
                    'regional' => $currentRegional,
                    'phone_number' => self::phoneFromRow($row),
                    'work_duration' => self::durationFromRow($row),
                ];
                continue;
            }
            if ($currentRegional === null || str_starts_with($label, 'Total ')
                || ! preg_match('/^(SG[.\d-]+)\s*-\s*(.+)$/i', $label, $match)) {
                continue;
            }

            $name = trim((string) preg_replace('/\s+(?:Tim Terpilih|\(Korwil[^)]*\))$/i', '', trim($match[2])));
            if ($name === '' || stripos($label, '(Korwil') !== false) {
                continue;
            }
            $nik = trim($match[1]);
            $nikKey = self::nikKey($nik);
            $staff[$nikKey] = [
                'nik' => $nik,
                'nik_key' => $nikKey,
                'name' => $name,
                'regional' => $currentRegional,
                'phone_number' => self::phoneFromRow($row),
                'work_duration' => self::durationFromRow($row),
            ];
        }

        $campuses = [];
        $staffCampuses = [];
        $campusRows = (array) ($reports['Sebar Brosur']['tables'][0] ?? $reports['Pasang Spanduk']['tables'][0] ?? []);
        $currentRegional = null;
        foreach ($campusRows as $row) {
            $section = trim((string) ($row[0] ?? ''));
            if (preg_match('/^Regional\s+([1-7])\s*-/i', $section, $match)) {
                $currentRegional = in_array((int) $match[1], [1, 2, 3], true) ? 'Regional '.$match[1] : null;
                continue;
            }
            if ($currentRegional === null || ! preg_match('/^(SG[.\d-]+)\s*-\s*/i', trim((string) ($row[1] ?? '')), $match)) {
                continue;
            }
            $code = mb_strtolower(trim((string) ($row[2] ?? '')));
            $name = trim((string) ($row[3] ?? ''));
            if ($code === '' || $code === '-' || $name === '' || $name === '-') {
                continue;
            }
            $nikKey = self::nikKey($match[1]);
            $campuses[$code] = ['code' => $code, 'name' => $name, 'regional' => $currentRegional];
            $staffCampuses[$nikKey] ??= [];
            if (! in_array($code, $staffCampuses[$nikKey], true)) {
                $staffCampuses[$nikKey][] = $code;
            }
        }

        return ['staff' => $staff, 'coordinators' => $coordinators, 'campuses' => $campuses, 'staff_campuses' => $staffCampuses];
    }

    /** @return array<string,string> */
    private static function syncCampuses(array $campuses): array
    {
        $labels = [];
        foreach ($campuses as $campus) {
            $existing = DB::table('partner_campuses')->whereRaw('LOWER(kode_kampus) = ?', [$campus['code']])->first();
            if ($existing) {
                DB::table('partner_campuses')->where('id', $existing->id)->update(['wilayah' => $campus['regional']]);
                $labels[$campus['code']] = (string) ($existing->display_name ?: $existing->name);
                continue;
            }

            $id = DB::table('partner_campuses')->insertGetId([
                'name' => $campus['name'],
                'display_name' => $campus['name'],
                'kode_kampus' => $campus['code'],
                'address' => '',
                'wilayah' => $campus['regional'],
                'created_at' => now(),
            ]);
            $labels[$campus['code']] = $campus['name'];
        }

        return $labels;
    }

    private static function preferredCampus(string $current, array $assigned): ?string
    {
        foreach ($assigned as $label) {
            if (\App\Support\CampusMatcher::matches($current, $label)) {
                return $label;
            }
        }

        return $assigned[0] ?? null;
    }

    private static function username(string $nik, string $name): string
    {
        $username = str_replace('.', '', trim($nik));
        return $username !== '' ? $username : (preg_replace('/[^a-z0-9]+/i', '', mb_strtolower($name)) ?: 'rsmuser');
    }

    private static function nikKey(string $nik): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $nik));
    }

    private static function phoneFromRow(array $row): string
    {
        foreach (array_reverse($row) as $value) {
            $value = trim((string) $value);
            if (preg_match('/^(?:\+?62|0)[0-9\s-]{6,}$/', $value)) {
                return $value;
            }
        }
        return '';
    }

    private static function durationFromRow(array $row): string
    {
        foreach (array_reverse($row) as $value) {
            $value = trim((string) $value);
            if (preg_match('/\bTahun\b/i', $value)) {
                return $value;
            }
        }
        return '';
    }
}
