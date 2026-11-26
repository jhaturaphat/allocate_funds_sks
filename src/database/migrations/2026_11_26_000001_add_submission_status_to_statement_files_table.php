<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_files', function (Blueprint $table) {
            $table->string('submission_status', 20)
                ->default('pending')
                ->after('status');
            $table->dateTime('submitted_at')->nullable()->after('submission_status');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('submitted_at');
            $table->index('submission_status');
        });

        Schema::create('statement_file_submission_histories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();

            // จงใจไม่สร้าง Foreign Key เพื่อเก็บ Audit History ไว้ได้
            // แม้ภายหลังไฟล์ต้นทางจะถูกลบหลัง Admin คืนสถานะแล้ว
            $table->unsignedBigInteger('statement_file_id');
            $table->string('original_filename');
            $table->string('action', 30);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_username', 100)->nullable();
            $table->dateTime('acted_at');

            $table->index(['statement_file_id', 'acted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_file_submission_histories');

        Schema::table('statement_files', function (Blueprint $table) {
            $table->dropIndex(['submission_status']);
            $table->dropColumn([
                'submission_status',
                'submitted_at',
                'submitted_by',
            ]);
        });
    }
};
