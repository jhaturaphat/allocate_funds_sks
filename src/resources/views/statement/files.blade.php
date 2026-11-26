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

        @if (session('success'))
            <p class="notice notice--info" role="status">{{ session('success') }}</p>
        @endif

        @if (session('error'))
            <p class="notice notice--error" role="alert">{{ session('error') }}</p>
        @endif

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
                            <th scope="col">สถานะนำส่งเงิน</th>
                            <th scope="col">จัดการ</th>
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
                                <td>
                                    @if ($file->submission_status === 'submitted')
                                        <span class="field__hint">เสร็จสิ้น</span>

                                        @if (auth()->user()->is_admin)
                                            <form method="POST"
                                                  action="{{ route('admin.statement.files.reopen-submission', ['fileId' => $file->id]) }}"
                                                  onsubmit="return confirm('ต้องการคืนสถานะไฟล์นี้เป็นรอนำส่งเงินใช่หรือไม่?');">
                                                @csrf
                                                <button type="submit" class="btn btn--primary">
                                                    คืนสถานะ
                                                </button>
                                            </form>
                                        @endif
                                    @elseif ($file->allocation_amount !== null)
                                        <form method="POST"
                                              action="{{ route('statement.files.confirm-submission', ['fileId' => $file->id]) }}"
                                              onsubmit="return confirm('ยืนยันว่าได้นำส่งเงินของงวดนี้แล้วใช่หรือไม่? เมื่อยืนยันแล้วจะไม่สามารถลบไฟล์นี้ได้จนกว่า Admin จะคืนสถานะ');">
                                            @csrf
                                            <button type="submit" class="btn btn--primary"
                                                    aria-label="ยืนยันนำส่งเงินไฟล์ {{ $file->original_filename }}">
                                                ยืนยันนำส่งเงินแล้ว
                                            </button>
                                        </form>
                                    @else
                                        <span class="field__hint">รอระบุยอดเงิน</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($file->submission_status === 'submitted')
                                        <span class="field__hint">ล็อกการลบ</span>
                                    @else
                                        <form method="POST"
                                              action="{{ route('statement.files.destroy', ['fileId' => $file->id]) }}"
                                              onsubmit="return confirm('ยืนยันลบไฟล์นี้ใช่หรือไม่? ข้อมูลรายละเอียดที่นำเข้าและผลการประมวลผลของไฟล์นี้จะถูกลบถาวร และสามารถนำไฟล์เดิมกลับมาอัปโหลดใหม่ได้');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--danger"
                                                    aria-label="ลบไฟล์ {{ $file->original_filename }}">
                                                ลบ
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>                
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