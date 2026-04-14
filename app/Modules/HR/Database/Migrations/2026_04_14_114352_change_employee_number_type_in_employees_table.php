<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // تغيير الحقل من ديسيمال إلى نصي ليدعم التسلسل EMP-0000
            $table->string('employee_number')->change();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('employee_number', 18, 0)->change();
        });
    }
};
