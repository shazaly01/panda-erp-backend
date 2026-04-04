<?php

namespace Tests\Feature\HR;

use Tests\ApiTestCase;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Models\SalaryStructure;
use App\Modules\HR\Services\PayrollService;
use PHPUnit\Framework\Attributes\Test;

class PayrollEngineTest extends ApiTestCase
{
    protected PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. تشغيل الـ Seeders لضمان وجود القواعد والهيكل في قاعدة البيانات
        $this->seed(\App\Modules\HR\Database\Seeders\SalaryRulesSeeder::class);
        $this->seed(\App\Modules\HR\Database\Seeders\SalaryStructureSeeder::class);

        // تجهيز خدمة الرواتب
        $this->payrollService = new PayrollService();
    }

    #[Test]
    public function it_calculates_standard_payslip_correctly(): void
    {
        // 1. تجهيز موظف (استخدام حقل full_name)
        $employee = Employee::factory()->create([
            'full_name' => 'محمد أحمد علي السيد',
        ]);

        // جلب الهيكل القياسي الذي زرعناه
        $structure = SalaryStructure::where('name', 'هيكل الراتب القياسي')->first();

        // إنشاء العقد وربطه بالموظف
        Contract::create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'basic_salary' => 4000, // راتب أساسي 4000
            'start_date' => now(),
            'is_active' => true,
        ]);

        // 🔥 هام جداً: تحديث الموظف ليقرأ العقد الجديد من قاعدة البيانات 🔥
        $employee->refresh();

        // 2. تشغيل المحرك (بدون مدخلات خارجية)
        $result = $this->payrollService->previewPayslip($employee);

        // 3. التحقق من الأرقام
        // الحسابات اليدوية المتوقعة:
        // Basic: 4000
        // Housing: 4000 * 0.25 = 1000
        // Transport: 500 (ثابت)
        // -----------------------------
        // Total Allowances = 5500

        // GOSI: (4000 + 1000) * 0.10 = 500
        // Total Deductions = 500

        // Net Salary = 5500 - 500 = 5000

        $this->assertEquals(5500, $result['totals']['total_allowances']);
        $this->assertEquals(500, $result['totals']['total_deductions']);
        $this->assertEquals(5000, $result['totals']['net_salary']);
    }

    #[Test]
    public function it_handles_external_inputs_like_loans(): void
    {
        // 1. تجهيز موظف جديد
        $employee = Employee::factory()->create();
        $structure = SalaryStructure::where('name', 'هيكل الراتب القياسي')->first();

        // إنشاء عقد براتب 4000
        Contract::create([
            'employee_id' => $employee->id,
            'salary_structure_id' => $structure->id,
            'basic_salary' => 4000,
            'start_date' => now(),
            'is_active' => true,
        ]);

        // 🔥 هام جداً: تحديث الموظف 🔥
        $employee->refresh();

        // 2. تمرير مدخلات خارجية (سلفة بقيمة 200)
        $inputs = ['LOAN' => 200];

        // 3. تشغيل المحرك مع المدخلات
        $result = $this->payrollService->previewPayslip($employee, $inputs);

        // 4. التحقق
        // الصافي السابق (5000) - السلفة (200) = 4800
        $this->assertEquals(4800, $result['totals']['net_salary']);

        // التأكد من أن بند السلفة ظهر في التفاصيل
        $loanLine = collect($result['lines'])->firstWhere('code', 'LOAN');
        $this->assertNotNull($loanLine);
        $this->assertEquals(200, $loanLine['amount']);
    }
}
