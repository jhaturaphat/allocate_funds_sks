@extends('layouts.app')

@section('title', 'รายการกลุ่มโรคเรื้อรัง')

@section('content')
    <section class="card">
        <div class="list-header">
            <h1 class="page-title">รายการกลุ่มโรคเรื้อรัง</h1>
            <a href="{{ route('statement.index') }}">กลับหน้า Statement</a>
        </div>

        <p id="selected-disease" class="notice notice--info" hidden></p>

        @if ($diseases->isEmpty())
            <p class="field__hint">ไม่พบรายการกลุ่มโรคในหน้านี้</p>
        @else
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">รหัสโรค</th>
                            <th scope="col">ชื่อโรคภาษาไทย</th>
                            <th scope="col">ชื่อโรคภาษาอังกฤษ</th>
                            <th scope="col" class="number">คะแนน</th>
                            <th scope="col">เลือกโรค</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diseases as $disease)
                            <tr>
                                <td>{{ $disease->cd }}</td>
                                <td>{{ $disease->name_th }}</td>
                                <td>{{ $disease->name_en }}</td>
                                <td class="number">{{ $disease->score }}</td>
                                <td>
                                    <button type="button"
                                            class="btn btn--primary"
                                            data-select-disease
                                            data-cd="{{ $disease->cd }}"
                                            data-name-th="{{ $disease->name_th }}"
                                            data-name-en="{{ $disease->name_en }}"
                                            data-score="{{ $disease->score }}"
                                            aria-label="เลือก {{ $disease->name_th }}">
                                        เลือก
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="field__hint">
                แสดงรายการ {{ $diseases->firstItem() }}–{{ $diseases->lastItem() }}
                จากทั้งหมด {{ $diseases->total() }} รายการ
            </p>
        @endif

        @if ($diseases->hasPages())
            <nav class="page-links" aria-label="หน้ารายการกลุ่มโรค">
                @foreach ($diseases->getUrlRange(1, $diseases->lastPage()) as $page => $url)
                    @if ($page === $diseases->currentPage())
                        <span aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="ไปหน้า {{ $page }}">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            </nav>
        @endif
    </section>

    <script>
        // ฟังก์ชันนี้ทำงานเมื่อกดปุ่ม “เลือก” ของแต่ละแถว
        function selectDisease(disease) {
            const output = document.getElementById('selected-disease');

            output.textContent =
                `เลือก ${disease.cd} · ${disease.nameTh} (${disease.nameEn}) · ${disease.score} คะแนน`;
            output.hidden = false;

            // เพิ่มโค้ดของคุณตรงนี้ เช่น นำรหัสโรคไปใส่ในช่องของฟอร์ม
            // document.getElementById('disease_cd').value = disease.cd;
        }

        document.querySelectorAll('[data-select-disease]').forEach(button => {
            button.addEventListener('click', () => {
                const { cd, nameTh, nameEn, score } = button.dataset;
                selectDisease({ cd, nameTh, nameEn, score });
            });
        });
    </script>
@endsection