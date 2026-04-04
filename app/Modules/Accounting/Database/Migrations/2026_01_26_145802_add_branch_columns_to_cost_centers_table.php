<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            // هل هذا المركز يعتبر فرعاً مستقلاً؟ (له خزينة وترقيم خاص)
            $table->boolean('is_branch')->default(false)->after('parent_id');

            // الكود المختصر للترقيم (مثال: RY, JD, DAM)
            $table->string('code_prefix', 10)->nullable()->after('is_branch');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropColumn(['is_branch', 'code_prefix']);
        });
    }
};
