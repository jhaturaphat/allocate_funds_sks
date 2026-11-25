<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'กรุณากรอก Username',
            'password.required' => 'กรุณากรอกรหัสผ่าน',
        ]);

        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if ($user && ! $user->is_approved) {
            return back()
                ->withErrors(['username' => 'บัญชีของคุณอยู่ระหว่างรอ Admin อนุมัติ'])
                ->onlyInput('username');
        }

        if (! Auth::attempt($credentials, false)) {
            return back()
                ->withErrors(['username' => 'Username หรือรหัสผ่านไม่ถูกต้อง'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('auth_started_at', now()->timestamp);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'ออกจากระบบเรียบร้อยแล้ว');
    }
}
