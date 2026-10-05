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
     * @return list<array{key: string, title: string, items: list<array{key: string, label: string, icon: string}>}>
     */
    public static function sections(RsmUser $user): array
    {
        $sections = [
            ['key' => 'utama', 'title' => 'Utama', 'items' => array_values(array_filter([
                ['key' => 'dashboard', 'label' => 'Dashboard Utama', 'icon' => 'home'],
                in_array($user->role, [RsmUser::ROLE_STAFF, RsmUser::ROLE_KOORDINATOR, RsmUser::ROLE_SUPER_USER], true)
                    ? ['key' => 'staff-journey', 'label' => 'Journey Staff', 'icon' => 'flag']
                    : null,
                ['key' => 'forum', 'label' => 'Forum Diskusi', 'icon' => 'chat'],
            ]))],
            ['key' => 'pekerjaan', 'title' => 'Pekerjaan Saya', 'items' => array_values(array_filter([
                ['key' => 'crm', 'label' => 'CRM Leads', 'icon' => 'users'],
                ['key' => 'kegiatan', 'label' => 'Kegiatan Marketing', 'icon' => 'briefcase'],
                ['key' => 'aktivitas', 'label' => 'Aktivitas Lain', 'icon' => 'bolt'],
                ['key' => 'upload-konten-sosmed', 'label' => 'Upload Konten', 'icon' => 'cloud'],
            ]))],
            ['key' => 'kinerja', 'title' => 'Kinerja', 'items' => array_values(array_filter([
                ['key' => 'pencapaian', 'label' => 'Pencapaian Staff', 'icon' => 'chart-bar'],
                ['key' => 'closing-kampus', 'label' => 'Pencapaian Kampus', 'icon' => 'chart-bar'],
                RsmRole::canViewScoringTable($user) ? ['key' => 'scoring', 'label' => 'Scoring Tim', 'icon' => 'chart-bar'] : null,
                ['key' => 'badges', 'label' => 'League & Badge', 'icon' => 'trophy'],
                ['key' => 'konten', 'label' => 'Monitoring Konten', 'icon' => 'photo'],
            ]))],
            ['key' => 'perencanaan', 'title' => 'Perencanaan Tim', 'items' => array_values(array_filter([
                $user->role === RsmUser::ROLE_SUPER_USER ? ['key' => 'closing-target-simulation', 'label' => 'Simulasi Target', 'icon' => 'target'] : null,
                RsmRole::canViewJadwalKoordinator($user) ? ['key' => 'jadwal-koordinator', 'label' => 'Jadwal Koordinator', 'icon' => 'calendar'] : null,
                RsmRole::canManageTargets($user) ? ['key' => 'jadwal-personalia', 'label' => 'Jadwal Personalia', 'icon' => 'clipboard'] : null,
            ]))],
            ['key' => 'laporan', 'title' => 'Anggaran & Laporan', 'items' => [
                ['key' => 'anggaran', 'label' => 'Anggaran Iklan', 'icon' => 'currency'],
                ['key' => 'rekap', 'label' => 'Rekap Laporan', 'icon' => 'document'],
            ]],
            ['key' => 'administrasi', 'title' => 'Administrasi', 'items' => array_values(array_filter([
                RsmRole::canViewUsersPage($user) ? ['key' => 'users', 'label' => 'Kelola User', 'icon' => 'user-group'] : null,
                RsmRole::canSyncCollab($user) ? ['key' => 'sumber-collab', 'label' => 'Sumber Data Collab', 'icon' => 'cloud'] : null,
                $user->role !== RsmUser::ROLE_STAFF ? ['key' => 'role', 'label' => 'Peran & Log Aktivitas', 'icon' => 'shield'] : null,
            ]))],
            ['key' => 'akun', 'title' => 'Akun', 'items' => [
                ['key' => 'profile', 'label' => 'Profil Saya', 'icon' => 'user'],
                ['key' => 'password', 'label' => 'Ganti Password', 'icon' => 'lock'],
            ]],
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
            'forum' => 'Forum Diskusi',
            'staff-journey' => 'Journey Staff Unit',
            'pencapaian' => 'Pencapaian Staff',
            'jadwal-koordinator' => 'Jadwal Koordinator',
            'bdc-users' => 'BDC Marketing',
            'konten' => 'Monitoring Konten Kampus',
            'upload-konten-sosmed' => 'Upload Konten Sosmed',
            'kegiatan' => 'Kegiatan Marketing',
            'anggaran' => 'Anggaran & Laporan Iklan',
            'aktivitas' => 'Aktivitas Lain',
            'crm' => 'CRM Leads',
            'rekap' => 'Laporan & Rekap',
            'role' => 'Peran & Log Aktivitas',
            'password' => 'Ganti Password',
            'profile' => 'Profil Saya',
            'users' => 'Kelola User',
            'sumber-collab' => 'Sumber Data Collab',
            'jadwal-personalia' => 'Jadwal Personalia',
            'closing-kampus' => 'Pencapaian Kampus',
            'closing-target-simulation' => 'Simulasi Target Closing',
            'scoring' => 'Scoring',
            'badges' => 'League Season & Badge',
        ];
    }

    /** Keys gated to privileged management roles. */
    public static function isRestricted(string $key): bool
    {
        return in_array($key, ['users', 'sumber-collab', 'jadwal-personalia', 'closing-target-simulation'], true);
    }

    public static function isAllowed(string $key, RsmUser $user): bool
    {
        return match ($key) {
            'jadwal-koordinator' => RsmRole::canViewJadwalKoordinator($user),
            'jadwal-personalia' => RsmRole::canManageTargets($user),
            'users' => RsmRole::canViewUsersPage($user),
            'sumber-collab' => RsmRole::canSyncCollab($user),
            'scoring' => RsmRole::canViewScoringTable($user),
            'closing-target-simulation' => $user->role === RsmUser::ROLE_SUPER_USER,
            'staff-journey' => in_array($user->role, [RsmUser::ROLE_STAFF, RsmUser::ROLE_KOORDINATOR, RsmUser::ROLE_SUPER_USER], true),
            default => true,
        };
    }

    /** Every menu key routes here except the ones with a real page built already. */
    public static function routeFor(string $key): string
    {
        return match ($key) {
            'dashboard' => route('dashboard'),
            'forum' => route('forum'),
            'staff-journey' => route('staff-journey'),
            'anggaran' => route('anggaran'),
            'konten' => route('konten'),
            'upload-konten-sosmed' => route('upload-konten-sosmed'),
            'pencapaian' => route('pencapaian'),
            'kegiatan' => route('kegiatan'),
            'aktivitas' => route('aktivitas'),
            'crm' => route('crm'),
            'rekap' => route('rekap'),
            'jadwal-personalia' => route('jadwal-personalia'),
            'users' => route('users'),
            'jadwal-koordinator' => route('jadwal-koordinator'),
            'sumber-collab' => route('sumber-collab'),
            'bdc-users' => route('bdc-users'),
            'role' => route('role'),
            'closing-kampus' => route('closing-kampus'),
            'closing-target-simulation' => route('closing-target-simulation'),
            'scoring' => route('scoring'),
            'badges' => route('badges'),
            'profile' => route('profile'),
            'password' => route('password.edit'),
            default => route('placeholder', $key),
        };
    }
}
