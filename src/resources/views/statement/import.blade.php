@extends('layouts.app')

@section('title', 'นำเข้าข้อมูลสเตทเม้นประกันสังคม')
@section('page-class', 'page--narrow')

@section('content')
<section class="card">
    <h1 class="page-title">นำเข้าข้อมูลสเตทเม้นประกันสังคม</h1>

    @if ($errors->any())
    <div class="notice notice--error" role="alert">
        <h2 class="notice__title">กรุณาตรวจสอบไฟล์ที่เลือก</h2>

        <ul class="notice__list">
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if (session('results'))
    <h2 class="notice__title">ผลการนำเข้า</h2>

    @foreach (session('results') as $result)
    <div @class([ 'notice' , 'notice--error'=> str_contains($result, 'ข้าม เพราะเคยนำเข้าแล้ว'),
        'notice--info' => !str_contains($result, 'ข้าม เพราะเคยนำเข้าแล้ว'),
        ])>
        {{ $result }}
    </div>
    @endforeach
    @endif

    <form id="statement-import-form" method="POST" action="{{ route('statement.store') }}"
        enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label class="field__label" for="statement-files">เลือกไฟล์ Excel</label>
            <input class="field__control" id="statement-files" type="file" name="files[]" accept=".xls,.xlsx" multiple required>
            <small class="field__hint">เลือกได้สูงสุด 12 ไฟล์ ขนาดไม่เกิน 10 MB ต่อไฟล์</small>
        </div>

        <div class="table-scroll" id="statement-periods" hidden>
            <p>ตรวจสอบปีและงวดของแต่ละไฟล์ก่อนนำเข้า หากชื่อไฟล์ไม่มีปีและงวด ให้กรอกเองได้ครับ</p>

            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">ชื่อไฟล์</th>
                        <th scope="col">ปี (ค.ศ.)</th>
                        <th scope="col">งวดที่</th>
                    </tr>
                </thead>
                <tbody id="statement-period-rows"></tbody>
            </table>
        </div>

        <p class="field">
            <label class="field__label" for="allocation-amount">ยอดเงินจัดสรรต่อไฟล์ (บาท)</label>
            <input class="field__control" id="allocation-amount" type="number" name="allocation_amount" value="{{ old('allocation_amount') }}"
                min="0" step="0.01" placeholder="เช่น 209222.96" aria-describedby="amount-help">
            <small class="field__hint" id="amount-help">เลือก 1 ไฟล์ต้องกรอกยอดเงิน เลือกหลายไฟล์เว้นว่างได้</small>
        </p>

        <div class="actions">
            <button class="btn btn--primary" type="submit">นำเข้าข้อมูล</button>
            <a class="btn btn--primary" href="{{ url('/statement/files') }}">เลือกไฟล์เพื่อประมวลผล</a>
        </div>
    </form>
</section>

<!-- Javascript -->
<script>
(() => {
    const fileInput = document.getElementById('statement-files');
    const periodSection = document.getElementById('statement-periods');
    const rows = document.getElementById('statement-period-rows');
    const amount = document.getElementById('allocation-amount');
    const amountHelp = document.getElementById('amount-help');

    function addNumberField(row, name, label, value, min, max) {
        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'field__control';
        input.name = name;
        input.value = value;
        input.min = min;
        input.max = max;
        input.required = true;
        input.setAttribute('aria-label', label);
        row.insertCell().append(input);
    }

    function updateForm() {
        const files = Array.from(fileInput.files);
        rows.replaceChildren();
        periodSection.hidden = files.length === 0;
        amount.required = files.length === 1;

        fileInput.setCustomValidity(
            files.length > 12 ? 'กรุณาเลือกไม่เกิน 12 ไฟล์ต่อครั้ง' : ''
        );

        amountHelp.textContent = files.length === 1 ?
            'กรุณากรอกยอดเงินจัดสรรสำหรับไฟล์นี้' :
            'เลือกหลายไฟล์เว้นยอดว่างได้ หากกรอก ทุกไฟล์ที่เลือกจะใช้ยอดเดียวกัน';

        files.forEach((file, index) => {
            // ตัวอย่าง 11443_acddetail_202602.xls → ปี 2026 งวด 2
            const match = file.name.match(/(?:^|[^\d])(\d{4})(0[1-9]|1[0-2])\.xlsx?$/i);
            const year = match ? match[1] : '';
            const period = match ? Number(match[2]) : '';

            const row = rows.insertRow();
            row.insertCell().textContent = file.name;

            addNumberField(row, `periods[${index}][year]`,
                `ปีของไฟล์ ${file.name}`, year, 1000, 9999);
            addNumberField(row, `periods[${index}][period]`,
                `งวดของไฟล์ ${file.name}`, period, 1, 12);
        });
    }

    fileInput.addEventListener('change', updateForm);
    updateForm();
})();
</script>
@endsection