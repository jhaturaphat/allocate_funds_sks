<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_claim_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->unsignedBigInteger('import_file_id');
            $table->unsignedInteger('source_row');
            $table->string('hcode', 10)->nullable();
            $table->string('hproc', 10)->nullable();
            $table->date('dttran');
            $table->string('hn', 30);
            $table->string('pid', 13);
            $table->string('rid', 30);
            $table->string('invno', 100);
            $table->string('cd', 3);
            $table->string('casesid', 50);
            $table->unique(['import_file_id', 'source_row'], 'uq_file_row');
            $table->index('pid', 'idx_pid');
            $table->index('hn', 'idx_hn');
            $table->index(['cd', 'dttran'], 'idx_cd_dttran');
            $table->index('casesid', 'idx_casesid');
            $table->foreign('import_file_id', 'fk_claim_import_file')
                ->references('id')->on('statement_files')
                ->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_claim_details');
    }
};
