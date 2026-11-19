@extends('layouts.app')

@section('title', 'เลือกรายการสเตทเม้นเพื่อประมวลผล')

@section('content')
    <section class="card">
        <div class="list-header">
            <h1 class="page-title">เลือกรายการสเตทเม้นเพื่อประมวลผล</h1>

            <a href="{{ route('statement.index') }}" class="btn btn--primary">
                นำเข้าไฟล์
            </a>
        </div>

        @if ($files->isEmpty())
            <p class="notice notice--info">ไม่พบไฟล์สเตทเม้นในหน้านี้</p>
        @else
            <p class="field__hint">คลิกยอดจัดสรรเพื่อระบุหรือแก้ไขยอดเงิน แล้วกดประมวลผลเพื่อคำนวณยอดรายโรงพยาบาล</p>

            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">งวดที่</th>
                            <th scope="col">ชื่อไฟล์</th>
                            <th scope="col">วันที่นำเข้า</th>
                            <th scope="col" class="number">จำนวนรายการ</th>
                            <th scope="col" class="number">ยอดจัดสรร (บาท)</th>
                            <th scope="col">ประมวลผล</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>
                                    {{ $file->payment_period ?? 'ยังไม่ระบุงวด' }}
                                    @if ($file->payment_year !== null)
                                        ({{ $file->payment_year }})
                                    @endif
                                </td>
                                <td>{{ $file->original_filename }}</td>
                                <td>{{ $file->imported_at }}</td>
                                <td class="number">{{ $file->imported_rows }}</td>
                                <td class="number">
                                    <a href="{{ route('statement.amount.edit', ['fileId' => $file->id]) }}">
                                        {{ $file->allocation_amount ?? 'ระบุยอดเงิน' }}
                                    </a>
                                </td>
                                <td>
                                    @if ($file->allocation_amount !== null)
                                        <a href="{{ route('statement.summary', ['fileId' => $file->id]) }}"
                                           class="btn btn--primary"
                                           aria-label="ประมวลผลไฟล์ {{ $file->original_filename }}">ประมวลผล</a>
                                    @else
                                        <span class="field__hint">รอระบุยอดเงิน</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($files->hasPages())
                    <nav aria-label="เปลี่ยนหน้ารายการสเตทเม้น">
                        {{ $files->links() }}
                    </nav>
                @endif
            </div>

            <p class="field__hint">
                แสดงไฟล์ที่ {{ $files->firstItem() }}–{{ $files->lastItem() }}
                จากทั้งหมด {{ $files->total() }} ไฟล์
            </p>
        @endif

        @if ($files->hasPages())
            <nav class="page-links" aria-label="แบ่งหน้ารายการสเตทเม้น">
                @if (!$files->onFirstPage())
                    <a href="{{ $files->previousPageUrl() }}" rel="prev">ก่อนหน้า</a>
                @endif

                <span aria-current="page">
                    หน้า {{ $files->currentPage() }} จาก {{ $files->lastPage() }}
                </span>

                @if ($files->hasMorePages())
                    <a href="{{ $files->nextPageUrl() }}" rel="next">ถัดไป</a>
                @endif
            </nav>
        @endif
    </section>
@endsection