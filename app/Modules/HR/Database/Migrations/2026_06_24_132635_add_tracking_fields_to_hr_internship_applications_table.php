<?php

declare(strict_types=1);

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
        Schema::table('hr_internship_applications', function (Blueprint $table) {
            $table->string('tracking_code', 5)
                ->nullable()
                ->after('photo_path')
                ->comment('كود المتابعة المكون من 5 أرقام لتأمين ودخول المتدرب الخارجي');

            $table->string('approved_barcode')
                ->nullable()
                ->after('status')
                ->comment('نسخة من باركود المتدرب المولد في جدول الموظفين بعد قبول المشرف لطلبه ليعرض كـ QR Code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hr_internship_applications', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_code',
                'approved_barcode',
            ]);
        });
    }
};
