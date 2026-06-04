<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. تنظيف وتحديث أي سجلات قديمة تحتوي على قيم فارغة في حقل الهاتف لمنع خطأ الـ MySQL
        DB::statement("UPDATE users SET phone = CONCAT('05000000', id) WHERE phone IS NULL OR phone = ''");

        // 2. تعديل خصائص الحقول الحالية (تحويل الـ username ليكون اختيارياً والـ phone ليكون إجبارياً)
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->change();
            $table->string('phone')->nullable(false)->change();
        });

        // 3. إضافة الحقول الجديدة فقط إذا لم تكن موجودة مسبقاً
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('pending')->after('password');
            }

            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->dropColumn(['status', 'phone_verified_at']);
        });
    }
};
