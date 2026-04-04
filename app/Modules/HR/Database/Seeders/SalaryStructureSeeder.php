<?php

namespace App\Modules\HR\Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\HR\Models\SalaryStructure;
use App\Modules\HR\Models\SalaryRule;

class SalaryStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء الهيكل
        $structure = SalaryStructure::updateOrCreate(
            ['name' => 'هيكل الراتب القياسي'],
            ['description' => 'يشمل الأساسي، السكن، النقل، والتأمينات']
        );

        // 2. جلب القواعد التي زرعناها سابقاً
        $rules = SalaryRule::whereIn('code', [
            'BASIC', 'HOUSING', 'TRANS', 'GOSI', 'ABSENCE', 'LOAN', 'SOCIETY'
        ])->get();

        // 3. ربط القواعد بالهيكل مع تحديد الترتيب
        // الترتيب مهم: الأساسي (1) -> البدلات -> الخصومات -> الصافي
        $syncData = [];
        foreach ($rules as $rule) {
            // نستخدم الـ sequence المخزن في القاعدة كترتيب افتراضي
            // (أو يمكننا تحديده يدوياً هنا)
            $sequence = match($rule->code) {
                'BASIC' => 1,
                'HOUSING' => 2,
                'TRANS' => 3,
                'GOSI' => 10,
                'ABSENCE' => 11,
                'LOAN' => 12,
                'SOCIETY' => 13,
                default => 99
            };

            $syncData[$rule->id] = ['sequence' => $sequence];
        }

        // الحفظ في جدول structure_rules
        $structure->rules()->sync($syncData);
    }
}
