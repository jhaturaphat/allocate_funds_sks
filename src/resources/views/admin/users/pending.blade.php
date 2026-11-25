@extends('layouts.app')

@section('title', 'อนุมัติสมาชิก')

@section('content')
<section class="card">
    <div class="list-header">
        <h1 class="page-title">อนุมัติสมาชิก</h1>
        <a href="{{ route('dashboard') }}" class="btn btn--primary">กลับ Dashboard</a>
    </div>

    @if (session('success'))
        <p class="notice notice--info" role="status">{{ session('success') }}</p>
    @endif

    @if ($users->isEmpty())
        <p class="notice notice--info">ไม่มีสมาชิกที่รออนุมัติ</p>
    @else
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ชื่อ - นามสกุล</th>
                        <th>Username</th>
                        <th>วันที่สมัคร</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->name }} {{ $user->surname }}</td>
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.approve', $user) }}"
                                      onsubmit="return confirm('ยืนยันอนุมัติสมาชิก {{ $user->username }} ?')">
                                    @csrf
                                    <button type="submit" class="btn btn--primary">อนุมัติ</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            {{ $users->links() }}
        @endif
    @endif
</section>
@endsection
