<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diseases_group', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->string('cd', 3)->primary();
            $table->string('name_th');
            $table->string('name_en');
            $table->decimal('score', 5, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diseases_group');
    }
};
