@extends('layouts.app')

@section('title', 'แก้ไขยอดเงินจัดสรร')
@section('page-class', 'page--narrow')

@section('content')
<section class="card">
    <h1 class="page-title">
        แก้ไขยอดเงินจัดสรร
        @if ($file->payment_period !== null)
        งวดที่ {{ $file->payment_period }}
        @else
        (ยังไม่ระบุงวด)
        @endif

        @if ($file->payment_year !== null)
        ปี {{ $file->payment_year }}
        @endif
    </h1>

    <!-- <p>
        <strong>ชื่อไฟล์</strong> {{ $file->original_filename }}
    </p> -->

    <p>
        <strong>ไฟล์ #{{ $file->id }}</strong>
        {{ $file->original_filename }}
    </p>

    <form method="POST" action="{{ route('statement.amount.update', ['fileId' => $file->id]) }}">
        @csrf
        @method('PATCH')

        <div class="field">
            <label for="allocation_amount" class="field__label">
                ยอดเงินจัดสรรทั้งไฟล์ (บาท)
            </label>

            <input id="allocation-amount" class="field__control" type="text" name="allocation_amount"
                inputmode="decimal" pattern="[0-9]{1,16}([.][0-9]{1,2})?"
                value="{{ old('allocation_amount', $file->allocation_amount) }}" placeholder="เช่น 127556.62"
                title="กรอกตัวเลข ทศนิยมไม่เกิน 2 ตำแหน่ง โดยไม่ใส่เครื่องหมายจุลภาค" required>

            <p id="amount-help" class="field__hint">
                กรอกยอดรวมที่จะนำไปแบ่งให้แต่ละโรงพยาบาล เช่น 209222.96
            </p>

            @error('allocation_amount')
            <p id="amount-error" class="notice notice--error" role="alert">
                {{ $message }}
            </p>
            @enderror
        </div>

        <div class="actions">
            <a href="{{ route('statement.files') }}" class="btn">ยกเลิก</a>

            <button type="submit" class="btn btn--primary">
                บันทึกและดูผลประมวลผล
            </button>
        </div>
    </form>
</section>

<script>
(() => {
    const amount = document.getElementById('allocation-amount');
    const allowed = /^(?:[0-9]{1,16}(?:\.[0-9]{0,2})?)?$/;
    let previous = allowed.test(amount.value) ? amount.value : '';

    amount.addEventListener('input', () => {
        if (allowed.test(amount.value)) {
            previous = amount.value;
        } else {
            amount.value = previous;
        }
    });
})();
</script>

@endsection