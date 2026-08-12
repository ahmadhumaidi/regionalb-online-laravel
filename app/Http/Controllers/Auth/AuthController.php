<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('profile');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // is_active is included as a credential so it's enforced as a WHERE
        // clause by the Eloquent user provider, mirroring rsm_login()'s
        // `WHERE username = ? AND is_active = 1`.
        $attempt = Auth::attempt([
            'username' => trim($credentials['username']),
            'password' => $credentials['password'],
            'is_active' => true,
        ]);

        if (! $attempt) {
            return back()->withErrors([
                'username' => 'Username atau password salah.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->forget('impersonation.original_id');

        return redirect()->intended(route('profile'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
