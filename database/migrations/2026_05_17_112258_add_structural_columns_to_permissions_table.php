<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // جلب اسم الجدول ديناميكياً من ملف إعدادات حزمة Spatie
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            // إضافة الأعمدة الجديدة بعد عمود guard_name للترتيب المنطقي في قاعدة البيانات
            $table->string('module')->nullable()->after('guard_name')->comment('مثل: accounting, hr, system');
            $table->string('group_name')->nullable()->after('module')->comment('مثل: employees, journal_entry');
            $table->string('action_name')->nullable()->after('group_name')->comment('مثل: view, create, approve');
            $table->string('display_name')->nullable()->after('action_name')->comment('الاسم المعروض باللغة العربية');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn(['module', 'group_name', 'action_name', 'display_name']);
        });
    }
};
