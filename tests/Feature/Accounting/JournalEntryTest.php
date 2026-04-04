<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Spatie\Permission\Models\Permission;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    protected Account $debitAccount;
    protected Account $creditAccount;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpAccountant();

        // تحسين: استخدام تواريخ ديناميكية
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear   = Carbon::now()->endOfYear();

        // 1. تجهيز سنة مالية مفتوحة
        FiscalYear::factory()->create([
            'start_date' => $startOfYear,
            'end_date'   => $endOfYear,
            'status'     => FiscalYearStatus::Open,
        ]);

        // 2. تجهيز حسابين للتجربة
        $this->debitAccount  = Account::factory()->create(['name' => 'Debit Account', 'code' => '101']);
        $this->creditAccount = Account::factory()->create(['name' => 'Credit Account', 'code' => '102']);

        // 3. تجهيز إعدادات التسلسل
        DB::table('sequences')->insert([
            'model'           => JournalEntry::class,
            'branch_id'       => null,
            'format'          => 'JE-{Y}-{000000}',
            'reset_frequency' => 'yearly',
            'next_value'      => 1,
            'current_year'    => now()->year,
            'current_month'   => now()->month,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    #[Test]
    public function an_accountant_can_create_balanced_draft_entry(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $data = [
            'date'        => now()->format('Y-m-d'), // تاريخ اليوم
            'description' => 'قيد افتتاحي',
            'details'     => [
                ['account_id' => $this->debitAccount->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 100],
            ]
        ];

        $response = $this->postJson('/api/v1/accounting/journal-entries', $data);

        $response->assertCreated();

        // التحقق من الرأس
        $this->assertDatabaseHas('journal_entries', [
            'description' => 'قيد افتتاحي',
            'status'      => EntryStatus::Draft->value,
        ]);

        // تحسين: التحقق من وجود التفاصيل أيضاً
        $this->assertDatabaseCount('journal_entry_details', 2);
    }

    #[Test]
    public function cannot_create_unbalanced_entry(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $data = [
            'date'    => now()->format('Y-m-d'),
            'details' => [
                ['account_id' => $this->debitAccount->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 50], // فرق 50
            ]
        ];

        $response = $this->postJson('/api/v1/accounting/journal-entries', $data);

        $response->assertUnprocessable() // 422
                 ->assertJsonValidationErrors(['balance']);
    }

    #[Test]
    public function cannot_create_entry_in_closed_period_or_no_fiscal_year(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        // تاريخ خارج السنة الحالية
        $invalidDate = now()->subYear()->format('Y-m-d');

        $data = [
            'date'    => $invalidDate,
            'details' => [
                ['account_id' => $this->debitAccount->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 100],
            ]
        ];

        $response = $this->postJson('/api/v1/accounting/journal-entries', $data);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['date']);
    }

    #[Test]
    public function an_accountant_can_post_a_draft_entry(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $entry = JournalEntry::factory()->create([
            'date'   => now()->format('Y-m-d'),
            'status' => EntryStatus::Draft,
        ]);

        // إضافة التفاصيل للقيد لأن الترحيل يتحقق من التوازن
        $entry->details()->create(['account_id' => $this->debitAccount->id, 'debit' => 100, 'credit' => 0]);
        $entry->details()->create(['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 100]);

        $response = $this->postJson("/api/v1/accounting/journal-entries/{$entry->id}/post");

        $response->assertOk();

        $entry->refresh();
        $this->assertEquals(EntryStatus::Posted, $entry->status);

        // التحقق من توليد الرقم حسب التسلسل
        // JE-{Y}-{000000} -> JE-202X-000001
        $expectedNumber = 'JE-' . now()->year . '-000001';
        $this->assertEquals($expectedNumber, $entry->entry_number);
    }

   #[Test]
    public function cannot_update_or_delete_posted_entry(): void
    {
        $this->actingAs($this->accountant, 'sanctum');

        $entry = JournalEntry::factory()->create([
            'date'   => now()->format('Y-m-d'),
            'status' => EntryStatus::Posted,
        ]);

        // محاولة التعديل
        $response = $this->putJson("/api/v1/accounting/journal-entries/{$entry->id}", [
            'description' => 'Hack attempt',
            'date' => now()->format('Y-m-d'),
             'details' => [
                ['account_id' => $this->debitAccount->id, 'debit' => 100, 'credit' => 0],
                ['account_id' => $this->creditAccount->id, 'debit' => 0, 'credit' => 100],
            ]
        ]);

        // --- التعديل هنا ---
        // نتوقع 403 لأن طبقة الحماية (Authorization) منعتنا قبل الوصول للخدمة
        $response->assertForbidden();
        // -------------------

        // محاولة الحذف
        $deleteResponse = $this->deleteJson("/api/v1/accounting/journal-entries/{$entry->id}");

        // --- التعديل هنا ---
        $deleteResponse->assertForbidden();
        // -------------------
    }

    // --- (الكود الخاص بـ setUpAccountant كما هو ممتاز) ---
    protected function setUpAccountant()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $guard = 'api';
        Permission::firstOrCreate(['name' => 'journal_entry.create', 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'journal_entry.update', 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'journal_entry.delete', 'guard_name' => $guard]);
        Permission::firstOrCreate(['name' => 'journal_entry.post',   'guard_name' => $guard]);

        $this->accountant = User::factory()->create();
        $this->accountant->givePermissionTo([
            'journal_entry.create', 'journal_entry.update', 'journal_entry.delete', 'journal_entry.post'
        ]);
    }
}
