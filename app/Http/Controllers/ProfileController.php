<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\GamificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = Auth::user();
        $query = RsmReport::query()->where('area', $user->area);
        if ($user->role === 'staff') {
            $query->where(function ($q) use ($user) { $q->where('user_id', $user->id)->orWhere('staff_name', $user->name); });
        } elseif ($user->role === 'koordinator' && $user->regional) {
            $query->where('wilayah', $user->regional);
        }
        $stats = [
            'reports' => (clone $query)->count(),
            'leads' => (clone $query)->sum('leads_count'),
            'closing' => (clone $query)->sum('closing_count'),
            'active_days' => (clone $query)->distinct('report_date')->count('report_date'),
        ];
        $dailyMissions = $this->dailyMissions($user);
        $reports = (clone $query)->latest('report_date')->latest('id')->limit(10)->get();
        $logs = RsmActivityLog::query()->where('area', $user->area)->where('actor_user_id', $user->id)->latest()->limit(10)->get();

        // Same point formula and badge thresholds as the Dashboard's "Arena
        // Performa Staff" leaderboard (GamificationService) - staff see
        // their own standing, koordinator/senior tier see their team's
        // pooled totals. See GamificationService::profileSummary().
        $gamification = GamificationService::profileSummary($user->area ?: 'Regional B', $user);
        $xp = $gamification['points'];
        ['level' => $level, 'level_progress' => $levelProgress, 'league' => $league] = GamificationService::levelFor($xp);
        $earnedBadges = $gamification['badges'];
        $badges = array_map(
            fn (string $name) => ['name' => $name, 'ok' => in_array($name, $earnedBadges, true)],
            GamificationService::BADGE_NAMES
        );
        $score = min(100, ($stats['reports'] * 4) + ($stats['leads'] * 2) + ($stats['closing'] * 8));

        return view('profile.index', compact('user', 'stats', 'reports', 'logs', 'xp', 'level', 'levelProgress', 'league', 'score', 'badges', 'dailyMissions'));
    }

    private function dailyMissions(RsmUser $user): array
    {
        $area = $user->area ?: 'Regional B';
        $today = now()->toDateString();
        $filters = [
            'date_from' => $today,
            'date_to' => $today,
            'wilayah' => '',
            'unit_name' => '',
            'staff_name' => (string) $user->name,
        ];

        $dailyReports = RsmReport::query()
            ->where('area', $area)
            ->whereDate('report_date', $today)
            ->when($user->role === 'staff', fn ($q) => $q->where(fn ($inner) => $inner->where('user_id', $user->id)->orWhere('staff_name', $user->name)))
            ->when($user->role === 'koordinator' && $user->regional, fn ($q) => $q->where('wilayah', $user->regional))
            ->with('adLeads')
            ->get();

        $followUps = $dailyReports->sum(function (RsmReport $report): int {
            if ($report->adLeads->isEmpty()) {
                return 0;
            }

            return $report->adLeads
                ->filter(fn ($lead) => filled($lead->follow_up_result) || filled($lead->progress_status))
                ->count();
        });
        $otherActivities = $dailyReports->where('report_type', RsmReport::TYPE_OTHER)->count();
        $shareFb = CollabMetricsService::personalTotal($filters, $area, $user, 'Share FB Group');
        $registrasi = CollabMetricsService::personalTotal($filters, $area, $user, 'Closing Personal Per Regional');

        return [
            $this->mission('login', 'Login', 1, 1),
            $this->mission('fu', 'Follow Up', $followUps, 30),
            $this->mission('share_fb', 'Share FB', $shareFb, 3),
            $this->mission('aktivitas_lain', 'Aktivitas Lain', $otherActivities, 1),
            $this->mission('reg', 'Closing Reg', $registrasi, 1),
        ];
    }

    private function mission(string $key, string $label, float $actual, float $target): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'actual' => $actual,
            'target' => $target,
            'progress' => $target > 0 ? min(100, round(($actual / $target) * 100)) : 0,
            'done' => $actual >= $target,
        ];
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['bio_text' => ['nullable', 'string', 'max:800'], 'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $user = Auth::user();
        if ($request->hasFile('profile_photo')) {
            if ($user->photo_path && str_starts_with($user->photo_path, 'profiles/')) Storage::disk('public')->delete($user->photo_path);
            $data['photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }
        $user->forceFill(array_filter(['bio_text' => $data['bio_text'] ?? null, 'photo_path' => $data['photo_path'] ?? null], static fn ($v) => $v !== null))->save();
        return back()->with('notice', 'Profil berhasil diperbarui.');
    }

    public function edit(): View { return view('profile.password', ['active' => 'password']); }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate(['old_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:6'], 'confirm_password' => ['required', 'string']]);
        if ($data['new_password'] !== $data['confirm_password']) throw ValidationException::withMessages(['confirm_password' => 'Konfirmasi password tidak sama.']);
        $user = Auth::user();
        if (! Hash::check($data['old_password'], $user->password_hash)) throw ValidationException::withMessages(['old_password' => 'Password lama tidak sesuai.']);
        $user->forceFill(['password_hash' => Hash::make($data['new_password']), 'must_change_password' => false])->save();
        return back()->with('notice', 'Password berhasil diperbarui.');
    }

    /** Serves legacy profile photos (photo_path outside Laravel's own public disk); mirrors ReportFormController::attachment(). */
    public function photo(RsmUser $user)
    {
        abort_unless(filled($user->photo_path), 404);

        if (Storage::disk('public')->exists($user->photo_path)) {
            return Storage::disk('public')->response($user->photo_path);
        }

        $legacyRoot = config('filesystems.legacy_public_root');
        $legacyPath = $legacyRoot ? realpath($legacyRoot.'/'.$user->photo_path) : false;
        abort_unless($legacyPath && str_starts_with($legacyPath, realpath($legacyRoot)), 404);

        return response()->file($legacyPath);
    }
}
