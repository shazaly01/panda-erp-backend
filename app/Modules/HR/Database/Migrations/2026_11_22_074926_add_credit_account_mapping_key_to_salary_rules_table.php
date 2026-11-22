<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة مفتاح التوجيه المحاسبي للطرف الدائن (الالتزام) الخاص بمساهمات الشركة
     */
    public function up(): void
    {
        if (!Schema::hasColumn('salary_rules', 'credit_account_mapping_key')) {
            Schema::table('salary_rules', function (Blueprint $table): void {
                $table->string('credit_account_mapping_key')
                    ->nullable()
                    ->after('account_mapping_key')
                    ->index();
            });
        }
    }

    /**
     * التراجع عن التعديل
     */
    public function down(): void
    {
        if (Schema::hasColumn('salary_rules', 'credit_account_mapping_key')) {
            Schema::table('salary_rules', function (Blueprint $table): void {
                $table->dropIndex(['credit_account_mapping_key']);
                $table->dropColumn('credit_account_mapping_key');
            });
        }
    }
};