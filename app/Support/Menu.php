<?php

namespace App\Support;

use App\Models\RsmUser;

/**
 * Grouped, iconized replacement for legacy dashboard.php's flat $menus +
 * $adminMenus arrays (lines 27-45). Visibility gates mirror the legacy
 * per-item checks exactly (dashboard.php:562, 566-579), which key off the
 * actually logged-in user's role, not the `?role=` preview — so callers
 * must pass Auth::user(), not the effective/previewed role.
 */
class Menu
{
    /**
     * @return list<array{title: string, items: list<array{key: string, label: string, icon: string}>}>
     */
    public static function sections(RsmUser $user): array
    {
        $sections = [
            [
                'title' => 'Utama',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard Utama', 'icon' => 'home'],
                ],
            ],
            [
                'title' => 'Kinerja & Tim',
                'items' => array_values(array_filter([
                    ['key' => 'pencapaian', 'label' => 'Pencapaian Staff', 'icon' => 'chart-bar'],
                    ['key' => 'closing-kampus', 'label' => 'Pencapaian Kampus', 'icon' => 'chart-bar'],
                    RsmRole::canViewScoringTable($user)
                        ? ['key' => 'scoring', 'label' => 'Scoring', 'icon' => 'chart-bar']
                        : null,
                    ['key' => 'badges', 'label' => 'Badge & Achievement', 'icon' => 'trophy'],
                    RsmRole::canViewJadwalKoordinator($user)
                        ? ['key' => 'jadwal-koordinator', 'label' => 'Jadwal Koordinator', 'icon' => 'calendar']
                        : null,
                    ['key' => 'bdc-users', 'label' => 'BDC Marketing', 'icon' => 'users'],
                ])),
            ],
            [
                'title' => 'Konten & Kegiatan',
                'items' => [
                    ['key' => 'konten', 'label' => 'Monitoring Konten Kampus', 'icon' => 'photo'],
                    ['key' => 'kegiatan', 'label' => 'Kegiatan Marketing', 'icon' => 'briefcase'],
                    ['key' => 'aktivitas', 'label' => 'Aktivitas Lain', 'icon' => 'bolt'],
                ],
            ],
            [
                'title' => 'Anggaran & Laporan',
                'items' => [
                    ['key' => 'anggaran', 'label' => 'Anggaran & Laporan Iklan', 'icon' => 'currency'],
                    ['key' => 'rekap', 'label' => 'Laporan & Rekap', 'icon' => 'document'],
                ],
            ],
            [
                'title' => 'Administrasi',
                'items' => array_values(array_filter([
                    RsmRole::canViewUsersPage($user)
                        ? ['key' => 'users', 'label' => 'Kelola User', 'icon' => 'user-group']
                        : null,
                    RsmRole::canSyncCollab($user)
                        ? ['key' => 'sumber-collab', 'label' => 'Sumber Data Collab', 'icon' => 'cloud']
                        : null,
                    RsmRole::canManageTargets($user)
                        ? ['key' => 'jadwal-personalia', 'label' => 'Jadwal Personalia', 'icon' => 'clipboard']
                        : null,
                ])),
            ],
            [
                'title' => 'Akun',
                'items' => [
                    ['key' => 'role', 'label' => 'Peran & Log Aktivitas', 'icon' => 'shield'],
                    ['key' => 'password', 'label' => 'Ganti Password', 'icon' => 'lock'],
                ],
            ],
        ];

        return array_values(array_filter($sections, fn (array $section) => $section['items'] !== []));
    }

    /** Page titles for every menu key, including ones not shown in the nav (mirrors $pageTitles, dashboard.php:46-47). */
    public static function title(string $key): string
    {
        return self::titles()[$key] ?? 'Halaman';
    }

    /** @return array<string, string> */
    public static function titles(): array
    {
        return [
            'dashboard' => 'Dashboard Utama',
            'pencapaian' => 'Pencapaian Staff',
            'jadwal-koordinator' => 'Jadwal Koordinator',
            'bdc-users' => 'BDC Marketing',
            'konten' => 'Monitoring Konten Kampus',
            'kegiatan' => 'Kegiatan Marketing',
            'anggaran' => 'Anggaran & Laporan Iklan',
            'aktivitas' => 'Aktivitas Lain',
            'rekap' => 'Laporan & Rekap',
            'role' => 'Peran & Log Aktivitas',
            'password' => 'Ganti Password',
            'targets' => 'Target Bulanan',
            'users' => 'Kelola User',
            'sumber-collab' => 'Sumber Data Collab',
            'jadwal-personalia' => 'Jadwal Personalia',
            'closing-kampus' => 'Pencapaian Kampus',
            'scoring' => 'Scoring',
            'badges' => 'Badge & Achievement',
        ];
    }

    /** Keys gated to the same role list as Target Bulanan / Kelola User / Sumber Data Collab (dashboard.php:568-579). */
    public static function isRestricted(string $key): bool
    {
        return in_array($key, ['targets', 'users', 'sumber-collab', 'jadwal-personalia'], true);
    }

    public static function isAllowed(string $key, RsmUser $user): bool
    {
        return match ($key) {
            'jadwal-koordinator' => RsmRole::canViewJadwalKoordinator($user),
            'targets', 'jadwal-personalia' => RsmRole::canManageTargets($user),
            'users' => RsmRole::canViewUsersPage($user),
            'sumber-collab' => RsmRole::canSyncCollab($user),
            'scoring' => RsmRole::canViewScoringTable($user),
            default => true,
        };
    }

    /** Every menu key routes here except the ones with a real page built already. */
    public static function routeFor(string $key): string
    {
        return match ($key) {
            'dashboard' => route('dashboard'),
            'anggaran' => route('anggaran'),
            'konten' => route('konten'),
            'pencapaian' => route('pencapaian'),
            'kegiatan' => route('kegiatan'),
            'aktivitas' => route('aktivitas'),
            'rekap' => route('rekap'),
            'targets' => route('targets'),
            'jadwal-personalia' => route('jadwal-personalia'),
            'users' => route('users'),
            'jadwal-koordinator' => route('jadwal-koordinator'),
            'sumber-collab' => route('sumber-collab'),
            'bdc-users' => route('bdc-users'),
            'role' => route('role'),
            'closing-kampus' => route('closing-kampus'),
            'scoring' => route('scoring'),
            'badges' => route('badges'),
            'password' => route('password.edit'),
            default => route('placeholder', $key),
        };
    }
}
