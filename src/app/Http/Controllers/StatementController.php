<?php

namespace App\Http\Controllers;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;
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
        $rows = $this->getAllocationRows($fileId, $budget);

        if (request()->query('format') === 'xlsx') {
            return $this->downloadSummary($file, $rows);
        }

        return view('statement.summary', compact('file', 'rows', 'budget'));
    }

    /**
     * สร้าง PDF หนังสือแจ้งจัดสรรให้โรงพยาบาลหนึ่งแห่ง
     */
    public function printHospital(int $fileId, string $hproc)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        $row = collect($rows)->first(
            fn ($item) => (string) $item->hproc === $hproc
        );

        abort_if($row === null, 404, 'ไม่พบข้อมูลโรงพยาบาลในไฟล์นี้');
        $this->assertPrintableRow($row);

        $pdfPath = $this->createHospitalPdf($row);

        return response()
            ->download($pdfPath, "allocation-{$fileId}-{$hproc}.pdf", [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * สร้าง PDF รวมทุกโรงพยาบาล โดยเรียงตาม hproc ตามผล SQL เดิม
     */
    public function printAllHospitals(int $fileId)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        abort_if($rows === [], 404, 'ไม่พบรายการจัดสรรสำหรับพิมพ์');

        foreach ($rows as $row) {
            $this->assertPrintableRow($row);
        }

        $pdfPath = $this->createCombinedHospitalPdf($rows);

        return response()
            ->download($pdfPath, "allocation-all-{$fileId}.pdf", [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * สร้างหนังสือ 1 ฉบับ พร้อมบัญชีรายละเอียดแนบท้ายทุกโรงพยาบาล
     * เป็นรูปแบบเอกสารเพิ่มเติมจากการพิมพ์หนังสือรายโรงพยาบาลแบบเดิม
     */
    public function printPeriodWithAppendix(int $fileId)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        abort_if($rows === [], 404, 'ไม่พบรายการจัดสรรสำหรับพิมพ์');

        foreach ($rows as $row) {
            $this->assertPrintableRow($row);
        }

        $pdfPath = $this->createPeriodPdf($file, $rows);

        return response()
            ->download($pdfPath, "allocation-period-{$fileId}.pdf", [
                'Content-Type' => 'application/pdf',
            ])
            ->deleteFileAfterSend(true);
    }


    /** ดาวน์โหลด Word หนังสือรายโรงพยาบาลหนึ่งแห่ง */
    public function downloadHospitalWord(int $fileId, string $hproc)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        $row = collect($rows)->first(
            fn ($item) => (string) $item->hproc === $hproc
        );

        abort_if($row === null, 404, 'ไม่พบข้อมูลโรงพยาบาลในไฟล์นี้');
        $this->assertPrintableRow($row);

        $wordPath = $this->createHospitalWord($row);

        return response()
            ->download($wordPath, "allocation-{$fileId}-{$hproc}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }

    /** ดาวน์โหลด Word ของทุกโรงพยาบาลเป็น ZIP (หนึ่งโรงพยาบาลต่อหนึ่งไฟล์ DOCX) */
    public function downloadAllHospitalWords(int $fileId)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        abort_if($rows === [], 404, 'ไม่พบรายการจัดสรรสำหรับดาวน์โหลด');

        foreach ($rows as $row) {
            $this->assertPrintableRow($row);
        }

        $zipPath = $this->createAllHospitalWordZip($fileId, $rows);

        return response()
            ->download($zipPath, "allocation-all-{$fileId}-word.zip", [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    /** ดาวน์โหลด Word หนังสือหนึ่งฉบับพร้อมบัญชีรายละเอียดแนบท้าย */
    public function downloadPeriodWord(int $fileId)
    {
        $file = $this->getCompletedStatementFile($fileId);
        $rows = $this->getAllocationRows($fileId, (string) $file->allocation_amount);

        abort_if($rows === [], 404, 'ไม่พบรายการจัดสรรสำหรับดาวน์โหลด');

        foreach ($rows as $row) {
            $this->assertPrintableRow($row);
        }

        $wordPath = $this->createPeriodWord($file, $rows);

        return response()
            ->download($wordPath, "allocation-period-{$fileId}.docx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend(true);
    }

    /** ดึงไฟล์นำเข้าที่สำเร็จและมีการกำหนดยอดจัดสรรแล้ว */
    private function getCompletedStatementFile(int $fileId): object
    {
        $file = DB::table('statement_files')
            ->select('id', 'original_filename', 'payment_year', 'payment_period', 'allocation_amount')
            ->where('id', $fileId)
            ->where('status', 'completed')
            ->first();

        abort_if($file === null, 404, 'ไม่พบไฟล์ที่นำเข้าสำเร็จ');
        abort_if($file->allocation_amount === null, 422, 'ไฟล์นี้ยังไม่ได้ระบุยอดเงินจัดสรร');

        return $file;
    }

    /** ตรวจสอบว่ารายการนี้สามารถสร้างหนังสือได้ */
    private function assertPrintableRow(object $row): void
    {
        abort_if(
            (int) $row->missing_scores > 0 || $row->final_amount === null,
            422,
            'ยังไม่สามารถพิมพ์ได้ เนื่องจากคะแนนกลุ่มโรคไม่ครบหรือคะแนนรวมเท่ากับศูนย์'
        );

        abort_if(
            trim((string) $row->hproc) === '' || trim((string) $row->hospital_name) === '',
            422,
            "ไม่พบชื่อโรงพยาบาลในตาราง hospcode (รหัส {$row->hproc})"
        );
    }

    /** SQL คำนวณยอดจัดสรรชุดเดียวกับหน้าสรุปและไฟล์ Excel */
    private function getAllocationRows(int $fileId, string $budget): array
    {
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
                    CASE WHEN missing_total = 0 THEN
                        CAST(total_score AS DECIMAL(30,12))
                        / NULLIF(grand_score, 0)
                    END AS calculated_value,
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

        return DB::select($sql, [$fileId, $budget]);
    }

    /** สร้างไฟล์ Word สำหรับโรงพยาบาลหนึ่งแห่ง */
    private function createHospitalWord(object $row): string
    {
        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        try {
            $tempDocx = $workDir . '/allocation.docx';
            $this->fillAllocationTemplate($row, $tempDocx);

            $targetDocx = $generatedDir . '/allocation-' . Str::uuid() . '.docx';
            File::move($tempDocx, $targetDocx);

            return $targetDocx;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** สร้าง ZIP ที่บรรจุ Word แยกไฟล์ตามโรงพยาบาลทุกแห่ง */
    private function createAllHospitalWordZip(int $fileId, array $rows): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException(
                'PHP Container ยังไม่มี ZipArchive กรุณาตรวจสอบ PHP extension zip'
            );
        }

        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';
        $zipPath = $generatedDir . '/allocation-all-' . $fileId . '-' . Str::uuid() . '.zip';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        $zip = new \ZipArchive();
        $opened = false;

        try {
            $result = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new RuntimeException('ไม่สามารถสร้างไฟล์ ZIP สำหรับ Word ได้');
            }
            $opened = true;

            foreach ($rows as $index => $row) {
                $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                $hproc = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $row->hproc);
                $docxPath = $workDir . '/hospital-' . $number . '.docx';
                $this->fillAllocationTemplate($row, $docxPath);

                $entryName = $number . '_allocation_' . $hproc . '.docx';
                if (!$zip->addFile($docxPath, $entryName)) {
                    throw new RuntimeException(
                        'ไม่สามารถเพิ่ม Word ของโรงพยาบาลรหัส ' . $row->hproc . ' ลง ZIP ได้'
                    );
                }
            }

            if (!$zip->close()) {
                $opened = false;
                throw new RuntimeException('ไม่สามารถปิดและบันทึกไฟล์ ZIP ได้');
            }
            $opened = false;

            if (!is_file($zipPath) || filesize($zipPath) === 0) {
                throw new RuntimeException('ไฟล์ ZIP ที่สร้างขึ้นว่างเปล่าหรือไม่สมบูรณ์');
            }

            return $zipPath;
        } catch (\Throwable $exception) {
            if ($opened) {
                $zip->close();
            }
            File::delete($zipPath);
            throw $exception;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** สร้าง Word หนังสือหนึ่งฉบับพร้อมตารางแนบท้าย */
    private function createPeriodWord(object $file, array $rows): string
    {
        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        try {
            $tempDocx = $workDir . '/allocation-period.docx';
            $this->fillPeriodTemplate($file, $rows, $tempDocx);

            $targetDocx = $generatedDir . '/allocation-period-' . Str::uuid() . '.docx';
            File::move($tempDocx, $targetDocx);

            return $targetDocx;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** สร้าง PDF สำหรับโรงพยาบาลหนึ่งแห่ง */
    private function createHospitalPdf(object $row): string
    {
        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        try {
            $docxPath = $workDir . '/allocation.docx';
            $this->fillAllocationTemplate($row, $docxPath);
            $this->convertDocxFilesToPdf([$docxPath], $workDir);

            $sourcePdf = $workDir . '/allocation.pdf';
            $targetPdf = $generatedDir . '/allocation-' . Str::uuid() . '.pdf';
            File::move($sourcePdf, $targetPdf);

            return $targetPdf;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** สร้าง PDF ของทุกโรงพยาบาลและรวมเป็นไฟล์เดียว */
    private function createCombinedHospitalPdf(array $rows): string
    {
        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        try {
            $docxPaths = [];
            $pdfPaths = [];

            foreach ($rows as $index => $row) {
                $number = str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
                $docxPath = $workDir . '/hospital-' . $number . '.docx';
                $this->fillAllocationTemplate($row, $docxPath);
                $docxPaths[] = $docxPath;
                $pdfPaths[] = $workDir . '/hospital-' . $number . '.pdf';
            }

            // แปลง Word ทั้งหมดด้วย LibreOffice หนึ่งรอบ
            $this->convertDocxFilesToPdf($docxPaths, $workDir);

            $combinedTempPath = $workDir . '/all-hospitals.pdf';

            if (count($pdfPaths) === 1) {
                File::copy($pdfPaths[0], $combinedTempPath);
            } else {
                // pdfunite มาจาก package poppler-utils ใน Docker Image
                $arguments = ['pdfunite', ...$pdfPaths, $combinedTempPath];
                $process = new Process($arguments);
                $process->setTimeout(120);
                $process->run();

                if (!$process->isSuccessful() || !is_file($combinedTempPath)) {
                    throw new RuntimeException(
                        'ไม่สามารถรวมไฟล์ PDF ได้: ' . $process->getErrorOutput()
                    );
                }
            }

            $targetPdf = $generatedDir . '/allocation-all-' . Str::uuid() . '.pdf';
            File::move($combinedTempPath, $targetPdf);

            return $targetPdf;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * สร้างหนังสือ 1 ฉบับ โดยมีตารางรายละเอียดโรงพยาบาลแนบท้าย
     */
    private function createPeriodPdf(object $file, array $rows): string
    {
        $baseDir = storage_path('app/documents');
        $workDir = $baseDir . '/temp/' . Str::uuid();
        $generatedDir = $baseDir . '/generated';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($generatedDir);

        try {
            $docxPath = $workDir . '/allocation-period.docx';
            $this->fillPeriodTemplate($file, $rows, $docxPath);
            $this->convertDocxFilesToPdf([$docxPath], $workDir);

            $sourcePdf = $workDir . '/allocation-period.pdf';
            $targetPdf = $generatedDir . '/allocation-period-' . Str::uuid() . '.pdf';

            if (!is_file($sourcePdf)) {
                throw new RuntimeException('ไม่พบไฟล์ PDF ที่แปลงจาก Word');
            }

            File::move($sourcePdf, $targetPdf);
            return $targetPdf;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /** เติมข้อมูลหนังสือและ clone แถวตารางให้ครบทุกโรงพยาบาล */
    private function fillPeriodTemplate(object $file, array $rows, string $docxPath): void
    {
        $templatePath = storage_path(
            'app/documents/templates/network_allocation_period.docx'
        );

        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'ไม่พบ Word Template: storage/app/documents/templates/network_allocation_period.docx'
            );
        }

        $template = new TemplateProcessor($templatePath);
        $budget = (string) $file->allocation_amount;

        $grandRecords = array_sum(array_map(
            static fn ($row) => (int) $row->total_records,
            $rows
        ));
        $grandScore = (string) ($rows[0]->grand_score ?? '0');

        // ข้อมูลในหน้าบันทึกข้อความ
        $template->setValue('print_date', $this->thaiPrintDate());
        $template->setValue('payment_period', (string) $file->payment_period);
        $template->setValue('allocation_amount', $this->formatAmountForDocument($budget));
        $template->setValue('allocation_amount_text', $this->thaiBahtText($budget));

        // ตารางแนบท้าย: แถวต้นแบบใน Word ต้องมี ${hcode}
        $template->cloneRow('hcode', count($rows));

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $template->setValue("hcode#{$line}", (string) $row->hproc);
            $template->setValue("hospital_name#{$line}", (string) $row->hospital_name);
            $template->setValue("total_records#{$line}", number_format((int) $row->total_records));
            $template->setValue("total_score#{$line}", $this->formatDecimalForDocument((string) $row->total_score));
            $template->setValue("final_amount#{$line}", $this->formatAmountForDocument((string) $row->final_amount));
        }

        // แถวรวมท้ายตาราง
        $template->setValue('grand_records', number_format($grandRecords));
        $template->setValue('grand_score', $this->formatDecimalForDocument($grandScore));
        $template->setValue('grand_amount', $this->formatAmountForDocument($budget));

        $template->saveAs($docxPath);
    }

    /** จัดรูปแบบเลขทศนิยมโดยไม่แปลงผ่าน float */
    private function formatDecimalForDocument(string $number): string
    {
        $negative = str_starts_with($number, '-');
        if ($negative) {
            $number = substr($number, 1);
        }

        [$integer, $fraction] = array_pad(explode('.', $number, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $integer = preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', ',', $integer);

        return ($negative ? '-' : '') . $integer . ($fraction !== '' ? '.' . $fraction : '');
    }

    /** เติมข้อมูลลงใน Word Template */
    private function fillAllocationTemplate(object $row, string $docxPath): void
    {
        $templatePath = storage_path(
            'app/documents/templates/network_allocation_individual.docx'
        );

        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'ไม่พบ Word Template: storage/app/documents/templates/network_allocation_individual.docx'
            );
        }

        $template = new TemplateProcessor($templatePath);
        $amount = (string) $row->final_amount;

        $template->setValue('print_date', $this->thaiPrintDate());
        $template->setValue('network_hospital', (string) $row->hospital_name);
        $template->setValue('allocation_amount', $this->formatAmountForDocument($amount));
        $template->setValue('allocation_amount_text', $this->thaiBahtText($amount));

        $template->saveAs($docxPath);
    }

    /** แปลง DOCX เป็น PDF ด้วย LibreOffice */
    private function convertDocxFilesToPdf(array $docxPaths, string $outputDir): void
    {
        $profileDir = $outputDir . '/lo-profile-' . Str::uuid();
        File::ensureDirectoryExists($profileDir);

        $arguments = [
            'libreoffice',
            '-env:UserInstallation=file://' . $profileDir,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDir,
            ...$docxPaths,
        ];

        $process = new Process($arguments);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                'LibreOffice แปลง Word เป็น PDF ไม่สำเร็จ: ' . $process->getErrorOutput()
            );
        }

        foreach ($docxPaths as $docxPath) {
            $pdfPath = $outputDir . '/' . pathinfo($docxPath, PATHINFO_FILENAME) . '.pdf';
            if (!is_file($pdfPath)) {
                throw new RuntimeException(
                    'LibreOffice ไม่พบไฟล์ PDF ที่สร้างจาก ' . basename($docxPath)
                );
            }
        }
    }

    /** วันที่ภาษาไทย เช่น 23 พฤศจิกายน 2569 */
    private function thaiPrintDate(): string
    {
        $date = now('Asia/Bangkok');
        $months = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];

        return $date->day . ' ' . $months[$date->month] . ' ' . ($date->year + 543);
    }

    /** จัดรูปแบบจำนวนเงินโดยไม่แปลงผ่าน float */
    private function formatAmountForDocument(string $amount): string
    {
        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $integer = preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', ',', $integer);
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return $integer . '.' . $fraction;
    }

    /** แปลงจำนวนเงินเป็นข้อความภาษาไทย เช่น หนึ่งพันบาทห้าสิบสตางค์ */
    private function thaiBahtText(string $amount): string
    {
        [$baht, $satang] = array_pad(explode('.', $amount, 2), 2, '0');
        $baht = ltrim($baht, '0');
        $baht = $baht === '' ? '0' : $baht;
        $satang = str_pad(substr($satang, 0, 2), 2, '0');

        $text = $this->thaiIntegerText($baht) . 'บาท';

        if ((int) $satang === 0) {
            return $text . 'ถ้วน';
        }

        return $text . $this->thaiIntegerText((string) (int) $satang) . 'สตางค์';
    }

    private function thaiIntegerText(string $number): string
    {
        $number = ltrim($number, '0');
        if ($number === '') {
            return 'ศูนย์';
        }

        $chunks = [];
        while ($number !== '') {
            $chunks[] = substr($number, -6);
            $number = strlen($number) > 6 ? substr($number, 0, -6) : '';
        }

        $chunks = array_reverse($chunks);
        $parts = [];
        $chunkCount = count($chunks);

        foreach ($chunks as $index => $chunk) {
            if ((int) $chunk === 0) {
                continue;
            }

            $millionCount = $chunkCount - $index - 1;
            $parts[] = $this->thaiSixDigitGroup($chunk) . str_repeat('ล้าน', $millionCount);
        }

        return implode('', $parts);
    }

    private function thaiSixDigitGroup(string $number): string
    {
        $digits = str_split(str_pad($number, 6, '0', STR_PAD_LEFT));
        $names = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
        $result = '';

        foreach ($digits as $index => $char) {
            $digit = (int) $char;
            if ($digit === 0) {
                continue;
            }

            $position = 5 - $index;

            if ($position === 1) {
                $result .= match ($digit) {
                    1 => 'สิบ',
                    2 => 'ยี่สิบ',
                    default => $names[$digit] . 'สิบ',
                };
                continue;
            }

            if ($position === 0 && $digit === 1 && $result !== '') {
                $result .= 'เอ็ด';
                continue;
            }

            $result .= $names[$digit] . $units[$position];
        }

        return $result;
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

    public function destroyFile(int $fileId)
    {
        $deletedFileName = DB::transaction(function () use ($fileId) {
        $file = DB::table('statement_files')
            ->where('id', $fileId)
            ->lockForUpdate()
            ->first();

        abort_if($file === null, 404, 'ไม่พบรายการสเตทเม้นที่ต้องการลบ');

        // ลบรายละเอียดที่นำเข้าทั้งหมดของไฟล์นี้ก่อน
        DB::table('statement_claim_details')
            ->where('import_file_id', $fileId)
            ->delete();

        // ลบข้อมูลประวัติไฟล์นำเข้า ซึ่งรวม file_sha256 ด้วย
        // หลังลบแล้วจึงสามารถอัปโหลดไฟล์เดิมซ้ำได้
        DB::table('statement_files')
            ->where('id', $fileId)
            ->delete();

        return $file->original_filename;
        });

        return redirect()->back()->with(
            'success',
            "ลบไฟล์ {$deletedFileName} และข้อมูลที่นำเข้าเรียบร้อยแล้ว สามารถอัปโหลดไฟล์เดิมใหม่ได้"
        );
    }
}