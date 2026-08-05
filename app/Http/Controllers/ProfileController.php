<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.password', ['active' => 'password']);
    }

    /**
     * Mirrors rsm_change_password()'s exact rules: min 6 chars, confirm
     * match, and old password re-verified against the current hash.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'string'],
        ]);

        if ($data['new_password'] !== $data['confirm_password']) {
            throw ValidationException::withMessages([
                'confirm_password' => 'Konfirmasi password tidak sama.',
            ]);
        }

        $user = Auth::user();

        if (! Hash::check($data['old_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'old_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->forceFill([
            'password_hash' => Hash::make($data['new_password']),
            'must_change_password' => false,
        ])->save();

        return back()->with('notice', 'Password berhasil diperbarui.');
    }
}
