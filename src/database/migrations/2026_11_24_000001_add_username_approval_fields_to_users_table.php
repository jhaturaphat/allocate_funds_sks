<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('surname', 100)->after('name');
            $table->string('username', 50)->unique()->after('surname');
            $table->boolean('is_approved')->default(false)->after('password');
            $table->boolean('is_admin')->default(false)->after('is_approved');
            $table->timestamp('approved_at')->nullable()->after('is_admin');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();

            // สมัครสมาชิกด้วย username ไม่จำเป็นต้องมี email
            $table->string('email')->nullable()->change();
        });

        // บัญชีที่มีอยู่ก่อนติดตั้งระบบนี้ให้ยังเข้าใช้งานได้
        // ผู้สมัครใหม่จะถูกกำหนด is_approved=false ใน Controller
        DB::table('users')->update(['is_approved' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropUnique(['username']);
            $table->dropColumn([
                'surname',
                'username',
                'is_approved',
                'is_admin',
                'approved_at',
                'approved_by',
            ]);
        });

        // คง email ให้ nullable เพื่อหลีกเลี่ยงปัญหาหากมีข้อมูล NULL แล้ว
    }
};
