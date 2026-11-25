<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'alpha_dash:ascii', 'min:4', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ], [
            'name.required' => 'กรุณากรอกชื่อ',
            'surname.required' => 'กรุณากรอกนามสกุล',
            'username.required' => 'กรุณากรอก Username',
            'username.alpha_dash' => 'Username ใช้ได้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข ขีดกลาง และขีดล่าง',
            'username.min' => 'Username ต้องมีอย่างน้อย 4 ตัวอักษร',
            'username.unique' => 'Username นี้มีผู้ใช้งานแล้ว',
            'password.min' => 'รหัสผ่านต้องมีอย่างน้อย 10 ตัวอักษร',
            'password.confirmed' => 'ยืนยันรหัสผ่านไม่ตรงกัน',
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'surname' => $validated['surname'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'is_approved' => false,
            'is_admin' => false,
        ]);

        return redirect()
            ->route('login')
            ->with('status', 'สมัครสมาชิกเรียบร้อยแล้ว กรุณารอผู้ดูแลระบบอนุมัติก่อนเข้าสู่ระบบ');
    }
}
