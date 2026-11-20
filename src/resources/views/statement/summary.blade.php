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
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->hproc ?: 'ไม่ระบุ' }}</td>
                    <td>{{ $row->hospital_name ?? 'ไม่พบชื่อโรงพยาบาล' }}</td>
                    <td class="number">{{ $row->total_records }}</td>
                    <td class="number">
                        {{ $row->missing_scores > 0 ? 'คะแนนไม่ครบ' : $row->total_score }}
                    </td>
                    <td class="number">{{ $row->calculated_value ?? '—' }}</td>
                    <td class="number">{{ $row->final_amount ?? '—' }}</td>
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
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</section>
@endsection