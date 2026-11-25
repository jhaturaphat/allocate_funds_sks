
@php
    $user = auth()->user();
@endphp

<header class="site-navbar">
    <div class="site-navbar__inner">

        {{-- Brand --}}
        <a href="{{ route('dashboard') }}" class="site-navbar__brand">
            <span class="site-navbar__logo">SKS</span>
            <span class="site-navbar__brand-text">
                <strong>SKS Management</strong>
                <small>Social Security Management</small>
            </span>
        </a>

        {{-- Desktop Navigation --}}
        <nav class="site-navbar__links" aria-label="เมนูหลัก">

            <a href="{{ route('dashboard') }}"
               class="site-navbar__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                หน้าหลัก
            </a>

            <a href="{{ route('statement.index') }}"
               class="site-navbar__link {{ request()->routeIs('statement.index', 'statement.store') ? 'is-active' : '' }}">
                นำเข้าไฟล์
            </a>

            <a href="{{ route('statement.files') }}"
               class="site-navbar__link {{ request()->routeIs('statement.files', 'statement.summary', 'statement.amount.*') ? 'is-active' : '' }}">
                รายการสเตทเม้น
            </a>

            <a href="{{ route('diseases-group.index') }}"
               class="site-navbar__link {{ request()->routeIs('diseases-group.*') ? 'is-active' : '' }}">
                กลุ่มโรค
            </a>

            @if ($user->is_admin)
                <a href="{{ route('admin.users.pending') }}"
                   class="site-navbar__link {{ request()->routeIs('admin.*') ? 'is-active' : '' }}">
                    อนุมัติสมาชิก
                </a>
            @endif

        </nav>

        {{-- User Information --}}
        <div class="site-navbar__account">
            <span class="site-navbar__username">
                {{ $user->name }} {{ $user->surname }}
            </span>

            @if ($user->is_admin)
                <span class="site-navbar__badge">Admin</span>
            @else
                <span class="site-navbar__badge">Member</span>
            @endif

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="site-navbar__logout">
                    ออกจากระบบ
                </button>
            </form>
        </div>

        {{-- Mobile Menu --}}
        <details class="site-navbar__mobile">
            <summary aria-label="เปิดเมนู">
                <span class="site-navbar__hamburger">☰</span>
                เมนู
            </summary>

            <div class="site-navbar__mobile-panel">

                <a href="{{ route('dashboard') }}">หน้าหลัก</a>
                <a href="{{ route('statement.index') }}">นำเข้าไฟล์</a>
                <a href="{{ route('statement.files') }}">รายการสเตทเม้น</a>
                <a href="{{ route('diseases-group.index') }}">กลุ่มโรค</a>

                @if ($user->is_admin)
                    <a href="{{ route('admin.users.pending') }}">
                        อนุมัติสมาชิก
                    </a>
                @endif

                <div class="site-navbar__mobile-user">
                    {{ $user->name }} {{ $user->surname }}
                    <span>{{ $user->is_admin ? 'Admin' : 'Member' }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="site-navbar__mobile-logout">
                        ออกจากระบบ
                    </button>
                </form>

            </div>
        </details>

    </div>
</header>
