@extends('layouts.app')

@section('title', 'เข้าสู่ระบบ')

@section('content')
<section class="card" style="max-width: 460px; margin: 2rem auto;">
    <h1 class="page-title">เข้าสู่ระบบ</h1>

    @if (session('status'))
        <p class="notice notice--info" role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="notice notice--error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}"
                   autocomplete="username" required autofocus>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn--primary">เข้าสู่ระบบ</button>
    </form>

    <p class="field__hint" style="margin-top: 1rem;">
        ยังไม่มีบัญชี? <a href="{{ route('register') }}">สมัครสมาชิก</a>
    </p>
</section>
@endsection
