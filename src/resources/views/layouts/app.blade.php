
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'ระบบประกันสังคม')</title>

    <link rel="stylesheet"
      href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>

    {{-- แสดง Navbar เฉพาะผู้ที่ Login แล้ว --}}
    @auth
        @include('partials.navbar')
    @endauth

    {{-- เนื้อหาของแต่ละหน้า --}}
    <main class="page @yield('page-class')">
        @yield('content')
    </main>

    @include('partials.button-icons')

</body>
</html>
