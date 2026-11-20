<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_files', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('original_filename');
            $table->string('sheet_name');
            $table->char('file_sha256', 64)->unique();
            $table->string('status', 20);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->dateTime('imported_at');
            $table->unsignedSmallInteger('payment_year')->nullable();
            $table->unsignedTinyInteger('payment_period')->nullable();
            $table->decimal('allocation_amount', 18, 2)->nullable();
            $table->index(['payment_year', 'payment_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_files');
    }
};
