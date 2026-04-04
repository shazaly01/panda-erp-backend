<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class FiscalYearLogicTest extends ApiTestCase
{
    #[Test]
    public function check_date_returns_true_for_open_year()
    {
        // 1. إنشاء سنة مفتوحة (2025)
        FiscalYear::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => FiscalYearStatus::Open
        ]);

        // 2. فحص تاريخ داخل السنة
        $isValid = FiscalYear::checkDate('2025-06-15');

        // 3. النتيجة: يجب أن يكون مسموحاً
        $this->assertTrue($isValid);
    }

    #[Test]
    public function check_date_returns_false_for_closed_year()
    {
        // 1. إنشاء سنة مغلقة (2024)
        FiscalYear::factory()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => FiscalYearStatus::Closed // مغلقة
        ]);

        // 2. فحص تاريخ داخل السنة المغلقة
        $isValid = FiscalYear::checkDate('2024-06-15');

        // 3. النتيجة: ممنوع (لأنها مغلقة)
        $this->assertFalse($isValid);
    }

    #[Test]
    public function check_date_returns_false_for_date_outside_range()
    {
        // سنة 2025 مفتوحة
        FiscalYear::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => FiscalYearStatus::Open
        ]);

        // فحص تاريخ في 2026
        $isValid = FiscalYear::checkDate('2026-01-01');

        $this->assertFalse($isValid);
    }
}
