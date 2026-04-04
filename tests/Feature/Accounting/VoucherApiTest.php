<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class VoucherApiTest extends ApiTestCase
{
    private string $endpoint = '/api/v1/accounting/vouchers';

    protected Account $expenseAccount; // حساب مصروفات (كهرباء مثلاً)
    protected Account $revenueAccount; // حساب إيرادات
    protected Box $mainBox;            // الخزينة
    protected CostCenter $branch;      // الفرع
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. تجهيز السنة المالية (لأن السند يولد قيداً، والقيد يفحص التاريخ)
        FiscalYear::factory()->create([
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => FiscalYearStatus::Open
        ]);

        // 2. تجهيز العملة والفرع
        $this->currency = Currency::factory()->create(['code' => 'SAR', 'is_base' => true]);
        $this->branch = CostCenter::factory()->create(['name' => 'Main Branch', 'code_prefix' => 'RY']);

        // 3. تجهيز الحسابات
        $this->expenseAccount = Account::factory()->create(['name' => 'Electricity Exp']);
        $this->revenueAccount = Account::factory()->create(['name' => 'Sales Rev']);

        // 4. تجهيز الخزينة (وربطها بحساب مالي تلقائياً أو يدوياً)
        // سننشئ حساب للخزينة يدوياً هنا للسرعة
        $boxAccount = Account::factory()->create(['name' => 'Cash Box Account']);
        $this->mainBox = Box::factory()->create([
            'name' => 'Main Cash Box',
            'account_id' => $boxAccount->id,
            'branch_id' => $this->branch->id,
            'currency_id' => $this->currency->id
        ]);

        // 5. إعدادات التسلسل (Sequences) لنوعي السندات
        // لسند الصرف PAYMENT
        DB::table('sequences')->insert([
            'model' => 'PAYMENT',
            'branch_id' => $this->branch->id,
            'format' => 'PAY-{0000}',
            'next_value' => 1,
            'reset_frequency' => 'yearly',
            'current_year' => now()->year,
        ]);

        // لسند القبض RECEIPT
        DB::table('sequences')->insert([
            'model' => 'RECEIPT',
            'branch_id' => $this->branch->id,
            'format' => 'REC-{0000}',
            'next_value' => 1,
            'reset_frequency' => 'yearly',
            'current_year' => now()->year,
        ]);

        // **مهم جداً**: إعداد تسلسل القيود (Journal Entries) لأن الترحيل سينشئ قيداً
        DB::table('sequences')->insert([
            'model' => \App\Modules\Accounting\Models\JournalEntry::class,
            'format' => 'JE-{0000}',
            'next_value' => 1,
            'reset_frequency' => 'yearly',
            'current_year' => now()->year,
        ]);
    }

    #[Test]
    public function it_validates_that_details_sum_equals_total_amount()
    {
        $this->actingAsAccountant();

        $payload = [
            'branch_id' => $this->branch->id,
            'type' => VoucherType::Payment->value,
            'date' => now()->format('Y-m-d'),
            'box_id' => $this->mainBox->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,

            'amount' => 1000, // الإجمالي 1000

            'details' => [
                [
                    'account_id' => $this->expenseAccount->id,
                    'amount' => 900, // المجموع 900 فقط
                ]
            ]
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['amount']); // يجب أن يشتكي من عدم التطابق
    }

    #[Test]
    public function accountant_can_create_draft_payment_voucher()
    {
        $this->actingAsAccountant();

        $payload = [
            'branch_id' => $this->branch->id,
            'type' => VoucherType::Payment->value,
            'date' => now()->format('Y-m-d'),
            'box_id' => $this->mainBox->id, // الدفع من الخزينة
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
            'amount' => 500,
            'description' => 'دفع فاتورة كهرباء',
            'details' => [
                [
                    'account_id' => $this->expenseAccount->id,
                    'amount' => 500,
                    'description' => 'شهر يناير'
                ]
            ]
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertCreated()
                 ->assertJsonPath('data.status', 'draft')
                 ->assertJsonPath('data.number', 'PAY-0001'); // التأكد من التسلسل

        $this->assertDatabaseHas('vouchers', [
            'amount' => 500,
            'type' => 'payment'
        ]);
    }

    #[Test]
    public function posting_payment_voucher_creates_correct_journal_entry()
    {
        $this->actingAsAccountant();

        // 1. إنشاء سند صرف (مسودة)
        $voucher = Voucher::factory()->create([
            'type' => VoucherType::Payment,
            'amount' => 1000,
            'box_id' => $this->mainBox->id,
            'status' => VoucherStatus::Draft
        ]);

        // تفاصيل: مصروف كهرباء
        $voucher->details()->create([
            'account_id' => $this->expenseAccount->id,
            'amount' => 1000
        ]);

        // 2. ترحيل السند
        $response = $this->postJson("{$this->endpoint}/{$voucher->id}/post");

        $response->assertOk()
                 ->assertJsonPath('data.status', 'posted');

        // 3. التحقق من القيد المحاسبي الناتج
        // في سند الصرف: المصروف (مدين)، الخزينة (دائن)

        $journalEntry = \App\Modules\Accounting\Models\JournalEntry::latest()->first();

        $this->assertNotNull($journalEntry);

        // فحص الطرف الدائن (الخزينة)
        $this->assertDatabaseHas('journal_entry_details', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->mainBox->account_id, // حساب الخزينة
            'credit' => 1000,
            'debit' => 0
        ]);

        // فحص الطرف المدين (المصروف)
        $this->assertDatabaseHas('journal_entry_details', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->expenseAccount->id, // حساب المصروف
            'debit' => 1000,
            'credit' => 0
        ]);
    }

    #[Test]
    public function posting_receipt_voucher_creates_reverse_journal_entry()
    {
        $this->actingAsAccountant();

        // 1. إنشاء سند قبض (Receipt)
        $voucher = Voucher::factory()->create([
            'type' => VoucherType::Receipt,
            'amount' => 2000,
            'box_id' => $this->mainBox->id,
            'status' => VoucherStatus::Draft
        ]);

        // تفاصيل: إيراد مبيعات
        $voucher->details()->create([
            'account_id' => $this->revenueAccount->id,
            'amount' => 2000
        ]);

        // 2. ترحيل السند
        $this->postJson("{$this->endpoint}/{$voucher->id}/post")->assertOk();

        // 3. التحقق من القيد المحاسبي الناتج
        // في سند القبض: الخزينة (مدين)، الإيراد (دائن)

        $journalEntry = \App\Modules\Accounting\Models\JournalEntry::latest()->first();

        // فحص الطرف المدين (الخزينة زادت)
        $this->assertDatabaseHas('journal_entry_details', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->mainBox->account_id,
            'debit' => 2000,
            'credit' => 0
        ]);

        // فحص الطرف الدائن (الإيراد)
        $this->assertDatabaseHas('journal_entry_details', [
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->revenueAccount->id,
            'credit' => 2000,
            'debit' => 0
        ]);
    }
}
