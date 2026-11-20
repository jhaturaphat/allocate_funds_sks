<?php

namespace App\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class StatementController extends Controller
{
    public function index()
    {
        return view('statement.import');
    }
// ส่วนอัปโหลดไฟล์ Excel
    public function store(Request $request)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'periods' => ['required', 'array', 'min:1', 'max:12'],
            'periods.*.year' => ['required', 'integer', 'between:1900,9999'],
            'periods.*.period' => ['required', 'integer', 'between:1,12'],
            'allocation_amount' => [
                Rule::requiredIf(fn () => count((array) $request->file('files')) === 1),
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        $files = $validated['files'];
        $periods = $validated['periods'];
        $allocationAmount = $validated['allocation_amount'] ?? null;
        $results = [];

        // files[0] ต้องคู่กับ periods[0], files[1] คู่กับ periods[1] ตามลำดับ
        if (array_diff_key($files, $periods) || array_diff_key($periods, $files)) {
            return back()
                ->withErrors(['periods' => 'ข้อมูลปีและงวดไม่ตรงกับไฟล์ที่เลือก กรุณาเลือกไฟล์ใหม่'])
                ->withInput();
        }

        foreach ($files as $index => $file) {
            $paymentYear = (int) $periods[$index]['year'];
            $paymentPeriod = (int) $periods[$index]['period'];
            $filename = $file->getClientOriginalName();
            $hash = hash_file('sha256', $file->getRealPath());
            $workbook = null;

            try {
                // ตรวจซ้ำล่วงหน้า ส่วน Unique Index ป้องกันกรณีส่งพร้อมกัน
                if (DB::table('statement_files')
                    ->where('file_sha256', $hash)
                    ->exists()) {
                    $results[] = "{$filename}: ข้าม เพราะเคยนำเข้าแล้ว";
                    continue;
                }

                $workbook = IOFactory::load($file->getRealPath());
                $sheet = $workbook->getSheet(0);

                // อ่านหัวตารางจากแถวแรก
                $columns = [];

                foreach ($sheet->getRowIterator(1, 1) as $headerRow) {
                    foreach ($headerRow->getCellIterator() as $cell) {
                        $name = strtolower(trim((string) $cell->getValue()));

                        if ($name === '') {
                            continue;
                        }

                        if (isset($columns[$name])) {
                            throw new RuntimeException(
                                "หัวคอลัมน์ซ้ำ: {$name}"
                            );
                        }

                        $columns[$name] = $cell->getColumn();
                    }
                }

                $required = [
                    'hcode','hproc','dttran', 'hn', 'pid', 
                    'rid', 'invno', 'cd', 'casesid',
                ];

                foreach ($required as $name) {
                    if (!isset($columns[$name])) {
                        throw new RuntimeException(
                            "ไม่พบหัวคอลัมน์ {$name}"
                        );
                    }
                }

                $count = DB::transaction(function () use (
                    $sheet,
                    $columns,
                    $required,
                    $filename,
                    $hash,
                    $paymentYear,
                    $paymentPeriod,
                    $allocationAmount
                ) {
                    $fileId = DB::table('statement_files')->insertGetId([
                        'original_filename' => $filename,
                        'sheet_name' => $sheet->getTitle(),
                        'file_sha256' => $hash,
                        'status' => 'processing',
                        'imported_rows' => 0,
                        'imported_at' => now(),
                        'payment_year' => $paymentYear,
                        'payment_period' => $paymentPeriod,
                        'allocation_amount' => $allocationAmount,
                    ]);

                    $buffer = [];
                    $count = 0;
                    $lastRow = $sheet->getHighestDataRow();

                    for ($row = 2; $row <= $lastRow; $row++) {
                        $data = [];

                        foreach ($required as $name) {
                            $cell = $sheet->getCell(
                                $columns[$name] . $row
                            );

                            // ไฟล์นี้ควรเป็นข้อมูลจริง ไม่มีสูตร
                            if ($cell->getDataType() === 'f') {
                                throw new RuntimeException(
                                    "แถว {$row}: คอลัมน์ {$name} เป็นสูตร"
                                );
                            }

                            $data[$name] = trim(
                                (string) $cell->getFormattedValue()
                            );
                        }

                        // ข้ามแถวว่าง
                        if (count(array_filter(
                            $data,
                            fn ($value) => $value !== ''
                        )) === 0) {
                            continue;
                        }

                        $dateCell = $sheet->getCell(
                            $columns['dttran'] . $row
                        );

                        $rawDate = $dateCell->getValue();

                        if (
                            is_numeric($rawDate)
                            && ExcelDate::isDateTime($dateCell)
                        ) {
                            $data['dttran'] =
                                ExcelDate::excelToDateTimeObject($rawDate)
                                    ->format('Y-m-d');
                        } else {
                            // วันที่แบบข้อความ ต้องเป็น YYYY-MM-DD
                            $dateText = trim((string) $rawDate);
                            $date = DateTimeImmutable::createFromFormat(
                                '!Y-m-d',
                                $dateText
                            );

                            if (
                                !$date
                                || $date->format('Y-m-d') !== $dateText
                            ) {
                                throw new RuntimeException(
                                    "แถว {$row}: วันที่ต้องเป็น YYYY-MM-DD"
                                );
                            }

                            $data['dttran'] = $dateText;
                        }

                        // เช่น 1 → 001 และ 14 → 014
                        if (preg_match('/^\d{1,3}$/D', $data['cd'])) {
                            $data['cd'] = str_pad(
                                $data['cd'],
                                3,
                                '0',
                                STR_PAD_LEFT
                            );
                        }

                        $validator = Validator::make($data, [
                            'hcode' => ['required', 'string', 'max:10'],
                            'hproc' => ['required', 'string', 'max:10'],
                            'dttran' => ['required', 'date_format:Y-m-d'],
                            'hn' => ['required', 'string', 'max:10'],
                            'pid' => ['required', 'regex:/^\d{13}$/D'],
                            'rid' => ['required', 'string', 'max:30'],
                            'invno' => ['required', 'string', 'max:100'],
                            'cd' => ['required', 'regex:/^\d{3}$/D'],
                            'casesid' => ['required', 'string', 'max:50'],
                        ]);

                        if ($validator->fails()) {
                            // แสดงชื่อคอลัมน์ที่ผิด โดยไม่แสดงข้อมูลผู้ป่วย
                            $fields = implode(
                                ', ',
                                array_keys($validator->errors()->messages())
                            );

                            throw new RuntimeException(
                                "แถว {$row}: ตรวจสอบคอลัมน์ {$fields}"
                            );
                        }

                        $buffer[] = [
                            'import_file_id' => $fileId,
                            'source_row' => $row,
                            ...$data,
                        ];

                        $count++;

                        // บันทึกครั้งละ 500 แถว
                        if (count($buffer) >= 500) {
                            DB::table('statement_claim_details')
                                ->insert($buffer);

                            $buffer = [];
                        }
                    }

                    if ($buffer !== []) {
                        DB::table('statement_claim_details')->insert($buffer);
                    }

                    if ($count === 0) {
                        throw new RuntimeException('ไม่พบข้อมูลสำหรับนำเข้า');
                    }

                    DB::table('statement_files')
                        ->where('id', $fileId)
                        ->update([
                            'status' => 'completed',
                            'imported_rows' => $count,
                        ]);

                    return $count;
                });

                $results[] = "{$filename}: สำเร็จ {$count} แถว";
            } catch (QueryException $e) {
                $duplicate = ($e->errorInfo[1] ?? null) === 1062;

                $results[] = $duplicate
                    ? "{$filename}: ข้อมูลซ้ำ ไม่ได้นำเข้า"
                    : "{$filename}: บันทึกฐานข้อมูลไม่สำเร็จ";
            } catch (RuntimeException $e) {
                $results[] = "{$filename}: {$e->getMessage()}";
            } catch (Throwable $e) {
                $results[] = "{$filename}: อ่านไฟล์ไม่สำเร็จ กรุณาตรวจสอบไฟล์ Excel";
            } finally {
                if ($workbook !== null) {
                    $workbook->disconnectWorksheets();
                }
            }
        }

        return back()->with('results', $results);
    }

// ส่วนแก้ไขยอดเงินจัดสรร
    public function editAmount(int $fileId)
    {
        $file = DB::table('statement_files')
            ->select('id', 'original_filename', 'payment_period', 'payment_year', 'allocation_amount')
            ->where('id', $fileId)
            ->where('status', 'completed')
            ->first();

        abort_if($file === null, 404, 'ไม่พบไฟล์ที่นำเข้าสำเร็จ');

        return view('statement.edit-amount', compact('file'));
    }

    public function updateAmount(Request $request, int $fileId)
    {
        $validated = $request->validate([
            'allocation_amount' => [
                'bail',
                'required',
                'regex:/^\d{1,16}(?:\.\d{1,2})?$/D',
                'numeric',
                'gt:0',
            ],
        ], [
            'allocation_amount.required' => 'กรุณาระบุยอดเงินจัดสรร',
            'allocation_amount.regex'    => 'กรอกจำนวนเต็มไม่เกิน 16 หลัก ทศนิยมไม่เกิน 2 ตำแหน่ง และไม่ใส่เครื่องหมายจุลภาค',
            'allocation_amount.gt'       => 'ยอดเงินจัดสรรต้องมากกว่า 0 บาท',
        ]);

        $file = DB::table('statement_files')
            ->where('id', $fileId)
            ->where('status', 'completed');

        abort_unless($file->exists(), 404, 'ไม่พบไฟล์ที่นำเข้าสำเร็จ');

        $file->update([
            'allocation_amount' => (string) $validated['allocation_amount'],
        ]);

        return redirect()
            ->route('statement.summary', ['fileId' => $fileId])
            ->with('success', 'บันทึกยอดเงินจัดสรรเรียบร้อยแล้ว');
    }

    public function summary(int $fileId)
    {
        $file = DB::table('statement_files')
            ->select('id', 'original_filename', 'payment_year', 'payment_period', 'allocation_amount')
            ->where('id', $fileId)
            ->first();

        abort_if($file === null, 404, 'ไม่พบไฟล์สเตทเม้น');

        if ($file->allocation_amount === null) {
            return redirect()
                ->route('statement.index')
                ->withErrors([
                    'allocation_amount' =>
                        "ไฟล์ {$file->original_filename} ยังไม่ได้ระบุยอดเงินจัดสรร",
                ]);
        }

        // เก็บยอดเป็นข้อความจนถึง MySQL เพื่อรักษาค่าบาทและสตางค์
        $budget = (string) $file->allocation_amount;

        $sql = <<<'SQL'
            WITH group_data AS (
                SELECT
                    a.hproc,
                    COUNT(*) AS total_records,
                    COUNT(*) - COUNT(b.score) AS missing_scores,
                    SUM(b.score) AS total_score
                FROM statement_claim_details AS a
                LEFT JOIN diseases_group AS b
                    ON b.cd = a.cd
                WHERE a.import_file_id = ?
                GROUP BY a.hproc
            ),
            totals AS (
                SELECT
                    g.*,
                    SUM(g.total_score) OVER () AS grand_score,
                    SUM(g.missing_scores) OVER () AS missing_total,
                    ROW_NUMBER() OVER (ORDER BY g.hproc) AS rn,
                    COUNT(*) OVER () AS total_rows,
                    CAST(? AS DECIMAL(18,2)) AS budget
                FROM group_data AS g
            ),
            amounts AS (
                SELECT
                    t.*,

                    -- สัดส่วนคะแนนของโรงพยาบาลต่อคะแนนทั้งไฟล์
                    CASE WHEN missing_total = 0 THEN
                        CAST(total_score AS DECIMAL(30,12))
                        / NULLIF(grand_score, 0)
                    END AS calculated_value,

                    -- คำนวณเงินจากคะแนนโดยตรง แล้วปัดยอดเงินเป็นสตางค์
                    CASE WHEN missing_total = 0 THEN
                        ROUND(
                            CAST(total_score * budget AS DECIMAL(38,12))
                            / NULLIF(grand_score, 0),
                            2
                        )
                    END AS raw_amount
                FROM totals AS t
            ),
            allocated AS (
                SELECT
                    a.*,
                    CASE
                        WHEN missing_total > 0 OR COALESCE(grand_score, 0) = 0
                            THEN NULL

                        -- โรงพยาบาลสุดท้ายรับยอดคงเหลือให้รวมตรงกับยอดของไฟล์
                        WHEN rn = total_rows THEN
                            budget - COALESCE(
                                SUM(raw_amount) OVER (
                                    ORDER BY hproc
                                    ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                                ),
                                0.00
                            )

                        ELSE raw_amount
                    END AS final_amount
                FROM amounts AS a
            )
            SELECT
                a.hproc,
                h.name AS hospital_name,
                a.total_records,
                a.missing_scores,
                a.total_score,
                a.calculated_value,
                a.final_amount,
                a.grand_score,
                a.missing_total
            FROM allocated AS a
            LEFT JOIN hospcode AS h
                ON h.hospcode = a.hproc
            ORDER BY a.hproc
            SQL;

        $rows = DB::select($sql, [$fileId, $budget]);

        if (request()->query('format') === 'xlsx') {
            return $this->downloadSummary($file, $rows);
        }

        return view('statement.summary', compact('file', 'rows', 'budget'));
    }

    private function downloadSummary(object $file, array $rows)
    {
        $filename = "statement-summary-{$file->id}.xlsx";

        return response()->streamDownload(function () use ($file, $rows) {
            $book = new Spreadsheet();
            try {
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('ผลจัดสรร');
                $sheet->setCellValue('A1', 'ผลประมวลผลสเตทเม้นประกันสังคม');
                $sheet->mergeCells('A1:F1');
                $sheet->setCellValue('A2', 'ไฟล์ต้นทาง');
                $sheet->setCellValueExplicit('B2', $file->original_filename, DataType::TYPE_STRING);
                $sheet->fromArray(['ปี', $file->payment_year, 'งวดที่', $file->payment_period], null, 'A3');
                $sheet->setCellValue('A4', 'ยอดจัดสรรทั้งไฟล์ (บาท)');

                // Excel รักษาความแม่นยำได้ 15 หลัก เก็บค่าที่ยาวกว่านั้นเป็นข้อความ
                $writeDecimal = static function ($cell, $value) use ($sheet) {
                    if ($value === null) {
                        return;
                    }
                    $text = (string) $value;
                    $digits = ltrim(str_replace(['.', '-'], '', $text), '0');
                    $type = strlen($digits) > 15 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC;
                    $sheet->setCellValueExplicit($cell, $text, $type);
                };
                $writeDecimal('B4', $file->allocation_amount);
                $sheet->fromArray(['รหัส รพ.', 'โรงพยาบาล', 'จำนวนรายการ', 'คะแนนรวม',
                    'สัดส่วน', 'ยอดจัดสรร (บาท)'], null, 'A6');
                $line = 7;
                $records = 0;
                foreach ($rows as $row) {
                    $sheet->setCellValueExplicit("A{$line}", (string) $row->hproc, DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("B{$line}", (string) $row->hospital_name, DataType::TYPE_STRING);
                    $sheet->setCellValue("C{$line}", (int) $row->total_records);
                    $writeDecimal("D{$line}", $row->total_score);
                    $writeDecimal("E{$line}", $row->calculated_value);
                    $writeDecimal("F{$line}", $row->final_amount);
                    $records += (int) $row->total_records;
                    $line++;
                }
                $sheet->setCellValue("A{$line}", 'รวมทั้งไฟล์');
                $sheet->setCellValue("C{$line}", $records);
                if ($rows) {
                    $writeDecimal("D{$line}", $rows[0]->grand_score);
                    if ($rows[0]->final_amount !== null) {
                        $writeDecimal("F{$line}", $file->allocation_amount);
                    } else {
                        $sheet->setCellValue('A5', 'คำนวณยอดจัดสรรไม่ได้ กรุณาตรวจสอบคะแนน');
                    }
                }
                foreach (['B4', "D7:D{$line}", "F7:F{$line}"] as $range) {
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
                }
                $sheet->getStyle('A6:F6')->getFont()->setBold(true);
                $sheet->getStyle("A{$line}:F{$line}")->getFont()->setBold(true);
                foreach (['A' => 26, 'B' => 65, 'C' => 18, 'D' => 18, 'E' => 24, 'F' => 24] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
                $sheet->freezePane('A7');
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function files()
    {
        $files = DB::table('statement_files')
            ->select(
                'id',
                'original_filename',
                'payment_year',
                'payment_period',
                'imported_rows',
                'allocation_amount',
                'imported_at'
            )
            ->orderByDesc('payment_year')
            ->orderByRaw('payment_period IS NULL ASC')
            ->orderByRaw('CAST(payment_period AS UNSIGNED) ASC')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return view('statement.files', compact('files'));
    }
}