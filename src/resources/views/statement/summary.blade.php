@extends('layouts.app')

@section('title', 'ผลประมวลผลสเตทเม้นประกันสังคม')

@section('content')
<section class="card">
    <div class="list-header">
        <h1 class="page-title">ผลประมวลผลสเตทเม้นประกันสังคม</h1>

        <a href="{{ route('statement.index') }}" class="btn btn--primary">
            <span aria-hidden="true">📥</span>
            กลับหน้านำเข้า
        </a>
        <a href="{{ route('dashboard') }}" class="btn btn--primary">
            <span aria-hidden="true">📊</span>
            กลับหน้า Dashboard
        </a>
        <a class="btn btn--primary"
            href="{{ route('statement.summary', ['fileId' => $file->id, 'format' => 'xlsx']) }}">
            ส่งออก Excel
        </a>
    </div>

    <p>
        <strong>ไฟล์</strong> {{ $file->original_filename }}
        · <strong>รหัสนำเข้า</strong> {{ $file->id }}
        · <strong>ยอดจัดสรร</strong> <span class="text-lg">{{ $budget }}</span> บาท
    </p>

    @if (empty($rows))
        <p class="notice notice--info">ยังไม่มีรายการรายละเอียดของไฟล์นี้</p>
    @else
        @php
            $canPrintDocuments = $rows[0]->final_amount !== null
                && collect($rows)->every(fn ($row) =>
                    filled($row->hproc)
                    && filled($row->hospital_name)
                    && $row->final_amount !== null
                    && (int) $row->missing_scores === 0
                );
        @endphp

        @if ($rows[0]->missing_total > 0)
            <p class="notice notice--error">
                พบ {{ $rows[0]->missing_total }} รายการที่ไม่มีคะแนนในตารางกลุ่มโรค
                กรุณาตรวจรหัส cd ก่อนจัดสรรยอดเงิน
            </p>
        @elseif ($rows[0]->final_amount === null)
            <p class="notice notice--error">
                คะแนนรวมเท่ากับศูนย์ จึงไม่สามารถคำนวณสัดส่วนได้
            </p>
        @endif

        @if (session('success'))
            <p class="notice notice--info" role="status">{{ session('success') }}</p>
        @endif

        @if ($canPrintDocuments)
            <div class="list-header" aria-label="ดาวน์โหลดเอกสาร">
                {{-- รูปแบบ PDF เดิม --}}
                <a class="btn btn--primary"
                    href="{{ route('statement.print-all', ['fileId' => $file->id]) }}">
                    🖨 พิมพ์ทั้งหมด (PDF รายโรงพยาบาล)
                </a>
                <a class="btn btn--primary"
                    href="{{ route('statement.print-period', ['fileId' => $file->id]) }}">
                    🖨 พิมพ์หนังสือพร้อมบัญชีแนบท้าย (PDF)
                </a>

                {{-- รูปแบบ Word ที่เพิ่มเข้ามา --}}
                <a class="btn btn--primary"
                    href="{{ route('statement.word-all', ['fileId' => $file->id]) }}">
                    ⬇ ดาวน์โหลด Word ทั้งหมด (.zip)
                </a>
                <a class="btn btn--primary"
                    href="{{ route('statement.word-period', ['fileId' => $file->id]) }}">
                    ⬇ ดาวน์โหลด Word พร้อมบัญชีแนบท้าย
                </a>
            </div>
            <p class="notice notice--info">
                Word ทั้งหมดจะดาวน์โหลดเป็น ZIP ภายในประกอบด้วยไฟล์ Word แยกหนึ่งไฟล์ต่อหนึ่งโรงพยาบาล
                ส่วน Word พร้อมบัญชีแนบท้ายจะเป็นเอกสาร Word เพียงหนึ่งไฟล์
            </p>
        @endif

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">รหัส รพ.</th>
                        <th scope="col">โรงพยาบาล</th>
                        <th scope="col" class="number">จำนวนรายการ</th>
                        <th scope="col" class="number">คะแนนรวม</th>
                        <th scope="col" class="number">สัดส่วน</th>
                        <th scope="col" class="number">ยอดจัดสรร (บาท)</th>
                        <th scope="col">PDF</th>
                        <th scope="col">Word</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row->hproc ?: 'ไม่ระบุ' }}</td>
                            <td>{{ $row->hospital_name ?? 'ไม่พบชื่อโรงพยาบาล' }}</td>
                            <td class="number">{{ number_format((int) $row->total_records) }}</td>
                            <td class="number">
                                {{ $row->missing_scores > 0 ? 'คะแนนไม่ครบ' : $row->total_score }}
                            </td>
                            <td class="number">{{ $row->calculated_value ?? '—' }}</td>
                            <td class="number">{{ $row->final_amount ?? '—' }}</td>
                            <td>
                                @if ($row->final_amount !== null && filled($row->hproc) && filled($row->hospital_name) && (int) $row->missing_scores === 0)
                                    <a class="btn btn--primary"
                                        href="{{ route('statement.print', ['fileId' => $file->id, 'hproc' => $row->hproc]) }}">
                                        🖨 พิมพ์
                                    </a>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                            <td>
                                @if ($row->final_amount !== null && filled($row->hproc) && filled($row->hospital_name) && (int) $row->missing_scores === 0)
                                    <a class="btn btn--primary"
                                        href="{{ route('statement.word', ['fileId' => $file->id, 'hproc' => $row->hproc]) }}">
                                        ⬇ Word
                                    </a>
                                @else
                                    <span>—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" scope="row">รวมทั้งไฟล์</th>
                        <td class="number">{{ array_sum(array_column($rows, 'total_records')) }}</td>
                        <td class="number">
                            {{ $rows[0]->missing_total > 0 ? 'คะแนนไม่ครบ' : $rows[0]->grand_score }}
                        </td>
                        <td></td>
                        <td class="number">
                            {{ $rows[0]->final_amount === null ? '—' : $budget }}
                        </td>
                        <td></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>
@endsection
