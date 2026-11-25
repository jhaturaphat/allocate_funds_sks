@extends('layouts.app')

@section('title', 'สมัครสมาชิก')

@section('content')
<section class="card" style="max-width: 520px; margin: 2rem auto;">
    <h1 class="page-title">สมัครสมาชิก</h1>
    <p class="field__hint">หลังสมัครสมาชิก ต้องรอ Admin อนุมัติก่อนจึงจะเข้าสู่ระบบได้</p>

    @if ($errors->any())
        <div class="notice notice--error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}">
        @csrf
        <div class="field">
            <label for="name">ชื่อ</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}"
                   autocomplete="given-name" maxlength="100" required>
        </div>

        <div class="field">
            <label for="surname">นามสกุล</label>
            <input id="surname" name="surname" type="text" value="{{ old('surname') }}"
                   autocomplete="family-name" maxlength="100" required>
        </div>

        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}"
                   autocomplete="username" minlength="4" maxlength="50"
                   pattern="[A-Za-z0-9_-]+" required>
            <small class="field__hint">ใช้อักษรภาษาอังกฤษ ตัวเลข ขีดกลาง หรือขีดล่าง อย่างน้อย 4 ตัว</small>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   minlength="10" required>
            <small class="field__hint">อย่างน้อย 10 ตัวอักษร</small>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   autocomplete="new-password" minlength="10" required>
        </div>

        <button type="submit" class="btn btn--primary">สมัครสมาชิก</button>
    </form>

    <p class="field__hint" style="margin-top: 1rem;">
        มีบัญชีแล้ว? <a href="{{ route('login') }}">เข้าสู่ระบบ</a>
    </p>
</section>
@endsection
