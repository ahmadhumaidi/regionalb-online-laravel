<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
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
        $reports = (clone $query)->latest('report_date')->latest('id')->limit(10)->get();
        $logs = RsmActivityLog::query()->where('area', $user->area)->where('actor_user_id', $user->id)->latest()->limit(10)->get();
        $xp = ($stats['reports'] * 10) + ($stats['leads'] * 2) + ($stats['closing'] * 25);
        $level = max(1, (int) floor($xp / 200) + 1);
        $levelBase = ($level - 1) * 200;
        $levelProgress = min(100, (int) round((($xp - $levelBase) / 200) * 100));
        $league = $xp >= 5000 ? 'Diamond' : ($xp >= 2500 ? 'Platinum' : ($xp >= 1000 ? 'Gold' : ($xp >= 500 ? 'Silver' : 'Starter')));
        $score = min(100, ($stats['reports'] * 4) + ($stats['leads'] * 2) + ($stats['closing'] * 8));
        $badges = [['name'=>'First Report','ok'=>$stats['reports'] >= 1],['name'=>'Lead Hunter','ok'=>$stats['leads'] >= 25],['name'=>'Closer','ok'=>$stats['closing'] >= 5],['name'=>'Consistent','ok'=>$stats['active_days'] >= 10]];
        return view('profile.index', compact('user', 'stats', 'reports', 'logs', 'xp', 'level', 'levelProgress', 'league', 'score', 'badges'));
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
}
