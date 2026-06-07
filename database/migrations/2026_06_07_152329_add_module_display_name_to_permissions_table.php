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
        // جلب اسم جدول الصلاحيات ديناميكياً من إعدادات Spatie
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->string('module_display_name')
                  ->nullable()
                  ->after('module')
                  ->comment('الاسم المعروض للموديول باللغة العربية مثل: الموارد البشرية');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('module_display_name');
        });
    }
};
