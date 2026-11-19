@extends('layouts.app')

@section('title', 'ภาพรวมสเตทเม้นประกันสังคม')

@section('content')
<section class="card">
    <header class="list-header">
        <h1 class="page-title">ภาพรวมสเตทเม้นประกันสังคม</h1>
        <div class="actions">
            <a class="btn btn--primary" href="{{ route('statement.index') }}">นำเข้าไฟล์ Excel</a>
            <a class="btn btn--primary" href="{{ url('/statement/files') }}">ดูรายการทุกงวด</a>
        </div>
    </header>

    <p class="notice notice--info">
        นำเข้าแล้ว <strong>{{ $stats->total }}</strong> ไฟล์ ·
        ระบุยอดแล้ว <strong>{{ $stats->ready }}</strong> ไฟล์ ·
        <a href="#pending">รอระบุยอดเงิน {{ $stats->total - $stats->ready }} ไฟล์</a>
    </p>

    <h2>งวดล่าสุด</h2>
    @if ($recent->isEmpty())
        <p>ยังไม่มีไฟล์สเตทเม้นที่นำเข้าสำเร็จ</p>
    @else
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th scope="col">งวดที่ (ปี)</th><th scope="col">ชื่อไฟล์</th><th scope="col" class="number">ยอดจัดสรร (บาท)</th><th scope="col">ประมวลผล</th></tr></thead>
                <tbody>
                    @foreach ($recent as $file)
                        <tr>
                            <td>{{ $file->payment_period ?? 'ยังไม่ระบุงวด' }} ({{ $file->payment_year ?? 'ยังไม่ระบุปี' }})</td>
                            <td>{{ $file->original_filename }}</td>
                            <td class="number"><a href="{{ route('statement.amount.edit', ['fileId' => $file->id]) }}">{{ $file->allocation_amount ?? 'ระบุยอดเงิน' }}</a></td>
                            <td>
                                @if ($file->allocation_amount !== null)
                                    <a class="btn btn--primary" href="{{ route('statement.summary', ['fileId' => $file->id]) }}">ประมวลผล</a>
                                @else
                                    <span class="field__hint">รอระบุยอดเงิน</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 id="pending">ไฟล์ที่รอระบุยอดเงิน</h2>
    @forelse ($pending as $file)
        <p><a href="{{ route('statement.amount.edit', ['fileId' => $file->id]) }}">ระบุยอดเงิน · งวด {{ $file->payment_period ?? '–' }} ปี {{ $file->payment_year ?? '–' }} · {{ $file->original_filename }}</a></p>
    @empty
        <p class="field__hint">ไม่มีไฟล์ที่รอระบุยอดเงิน</p>
    @endforelse
    @if ($pending->hasPages())
        <nav class="page-links" aria-label="หน้าไฟล์ที่รอระบุยอดเงิน">
            @if ($pending->previousPageUrl()) <a href="{{ $pending->previousPageUrl() }}">ก่อนหน้า</a> @endif
            <span aria-current="page">{{ $pending->currentPage() }} / {{ $pending->lastPage() }}</span>
            @if ($pending->nextPageUrl()) <a href="{{ $pending->nextPageUrl() }}">ถัดไป</a> @endif
        </nav>
    @endif
</section>
@endsection
