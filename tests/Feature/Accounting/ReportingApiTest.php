<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;

class ReportingApiTest extends ApiTestCase
{
    protected Account $cashAccount;
    protected Account $salesAccount;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. إعداد السنة المالية
        FiscalYear::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
            'status' => FiscalYearStatus::Open
        ]);

        // 2. إعداد الحسابات
        $this->cashAccount = Account::factory()->create(['name' => 'Cash', 'code' => '101']);
        $this->salesAccount = Account::factory()->create(['name' => 'Sales', 'code' => '401']);

        // 3. إعداد التسلسل للقيود (مهم للترحيل)
        DB::table('sequences')->insert([
            'model' => JournalEntry::class,
            'format' => 'JE-{0000}',
            'next_value' => 1,
            'reset_frequency' => 'yearly',
            'current_year' => now()->year,
        ]);
    }

    #[Test]
    public function it_calculates_ledger_opening_balance_and_transactions_correctly()
    {
        $this->actingAsAccountant();

        // التاريخ المستهدف للتقرير: من يوم 10 إلى يوم 20
        $reportStartDate = now()->startOfYear()->addDays(10)->format('Y-m-d');
        $reportEndDate = now()->startOfYear()->addDays(20)->format('Y-m-d');

        // أ) حركة قديمة (يجب أن تدخل في الرصيد الافتتاحي)
        $this->createPostedEntry('Old Transaction', now()->startOfYear()->addDays(1), 1000);

        // ب) حركة داخل الفترة (يجب أن تظهر في التفاصيل)
        $this->createPostedEntry('In-Period Transaction 1', now()->startOfYear()->addDays(12), 500);
        $this->createPostedEntry('In-Period Transaction 2', now()->startOfYear()->addDays(15), 200);

        // ج) حركة مستقبلية (يجب ألا تظهر)
        $this->createPostedEntry('Future Transaction', now()->startOfYear()->addDays(25), 300);

        // طلب التقرير
        $response = $this->getJson("/api/v1/accounting/reports/ledger?account_id={$this->cashAccount->id}&start_date={$reportStartDate}&end_date={$reportEndDate}");

        $response->assertOk();

        // التحقق من القيم
        // 1. الرصيد الافتتاحي: يجب أن يكون 1000 (من الحركة القديمة)
        $this->assertEquals(1000, $response->json('opening_balance'));

        // 2. عدد الحركات: يجب أن يكون 2 فقط
        $this->assertCount(2, $response->json('transactions'));

        // 3. الرصيد الختامي: 1000 (افتتاحي) + 500 + 200 = 1700
        $this->assertEquals(1700, $response->json('closing_balance'));
    }

    #[Test]
    public function trial_balance_sums_debits_and_credits_correctly()
    {
        $this->actingAsAccountant();

        $start = now()->startOfYear()->format('Y-m-d');
        $end = now()->endOfYear()->format('Y-m-d');

        // قيد 1: نقدية (مدين 1000) / مبيعات (دائن 1000)
        $this->createPostedEntry('Sale 1', now(), 1000);

        // قيد 2: نقدية (مدين 500) / مبيعات (دائن 500)
        $this->createPostedEntry('Sale 2', now(), 500);

        $response = $this->getJson("/api/v1/accounting/reports/trial-balance?start_date={$start}&end_date={$end}");

        $response->assertOk();

        $data = collect($response->json('data'));
        $totals = $response->json('totals');

        // فحص حساب النقدية (إجمالي المدين 1500)
        $cashRow = $data->where('account_id', $this->cashAccount->id)->first();
        $this->assertEquals(1500, $cashRow['debit']);
        $this->assertEquals(0, $cashRow['credit']);

        // فحص حساب المبيعات (إجمالي الدائن 1500)
        $salesRow = $data->where('account_id', $this->salesAccount->id)->first();
        $this->assertEquals(0, $salesRow['debit']);
        $this->assertEquals(1500, $salesRow['credit']);

        // فحص توازن الميزان ككل
        $this->assertEquals(1500, $totals['debit']);
        $this->assertEquals(1500, $totals['credit']);
    }

    // دالة مساعدة لإنشاء قيود مرحلة بسرعة
    private function createPostedEntry($desc, $date, $amount)
    {
        $entry = JournalEntry::factory()->create([
            'date' => $date,
            'status' => EntryStatus::Posted,
            'description' => $desc
        ]);

        $entry->details()->create([
            'account_id' => $this->cashAccount->id,
            'debit' => $amount,
            'credit' => 0
        ]);

        $entry->details()->create([
            'account_id' => $this->salesAccount->id,
            'debit' => 0,
            'credit' => $amount
        ]);

        return $entry;
    }
}
