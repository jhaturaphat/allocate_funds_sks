
@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')

@section('page-class', 'page--auth')

@section('content')
<div class="auth-wrapper">

    <section class="auth-card">

        {{-- Brand --}}
        <div class="auth-brand">
            <div class="auth-brand__logo">SKS</div>

            <div class="auth-brand__text">
                <strong>SKS Management</strong>
                <span>Social Security Management</span>
            </div>
        </div>

        <div class="auth-heading">
            <h1>ยินดีต้อนรับ</h1>
            <p>เข้าสู่ระบบบริหารจัดการข้อมูลประกันสังคม</p>
        </div>

        {{-- Status --}}
        @if (session('status'))
            <div class="notice notice--info" role="status">
                {{ session('status') }}
            </div>
        @endif

        {{-- Errors --}}
        @if ($errors->any())
            <div class="notice notice--error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Login Form --}}
        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <div class="field auth-field">
                <label for="username" class="field__label">
                    Username
                </label>

                <div class="auth-input-wrap">
                    <span class="auth-input-icon">
                        <i data-lucide="user"></i>
                    </span>

                    <input
                        id="username"
                        name="username"
                        type="text"
                        class="field__control auth-input"
                        value="{{ old('username') }}"
                        placeholder="กรอก Username ของคุณ"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="field auth-field">
                <label for="password" class="field__label">
                    Password
                </label>

                <div class="auth-input-wrap">
                    <span class="auth-input-icon">
                        <i data-lucide="lock"></i>
                    </span>

                    <input
                        id="password"
                        name="password"
                        type="password"
                        class="field__control auth-input"
                        placeholder="กรอกรหัสผ่าน"
                        autocomplete="current-password"
                        required
                    >
                </div>
            </div>
            <br>
            <button type="submit" class="btn btn--primary auth-submit">
                เข้าสู่ระบบ
                <i data-lucide="arrow-right"></i>
            </button>

        </form>

        <div class="auth-register">
            <span>ยังไม่มีบัญชีใช้งาน?</span>
            <a href="{{ route('register') }}">สมัครสมาชิก</a>
        </div>

        <div class="auth-footer">
            ระบบสำหรับบุคลากรที่ได้รับอนุมัติให้เข้าใช้งาน
        </div>

    </section>

</div>
@endsection
