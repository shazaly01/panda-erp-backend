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
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            // قمنا بإزالة العمود المكرر لأنه أُضيف بنجاح في الميجريشن السابق
            $table->string('group_display_name')
                  ->nullable()
                  ->after('group_name')
                  ->comment('الاسم المعروض للشاشة باللغة العربية مثل: ملفات الموظفين');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('permission.table_names.permissions', 'permissions');

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('group_display_name');
        });
    }
};
