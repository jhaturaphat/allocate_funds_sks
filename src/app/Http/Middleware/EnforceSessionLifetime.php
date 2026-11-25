<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionLifetime
{
    private const MAX_SESSION_AGE_SECONDS = 8 * 60 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $startedAt = (int) $request->session()->get('auth_started_at', 0);

            // Session เก่าที่ไม่มีเวลาเริ่มต้น: เริ่มนับจาก request นี้
            if ($startedAt <= 0) {
                $request->session()->put('auth_started_at', now()->timestamp);
            } elseif ((now()->timestamp - $startedAt) >= self::MAX_SESSION_AGE_SECONDS) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', 'เซสชันหมดอายุแล้ว กรุณาเข้าสู่ระบบใหม่');
            }
        }

        return $next($request);
    }
}
