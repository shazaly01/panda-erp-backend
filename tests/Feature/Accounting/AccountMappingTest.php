<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Services\AccountMappingService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class AccountMappingTest extends ApiTestCase
{
    protected AccountMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountMappingService();
        Cache::flush(); // تنظيف الكاش لضمان دقة الاختبار
    }

    #[Test]
    public function service_prioritizes_branch_mapping_over_general()
    {
        // 1. إعداد حساب عام وحساب خاص
        $generalAccount = Account::factory()->create(['name' => 'General Cash']);
        $branchAccount = Account::factory()->create(['name' => 'Branch Cash']);

        // 2. توجيه عام (بدون فرع)
        AccountMapping::create([
            'key' => 'cash_box',
            'name' => 'Cash',
            'account_id' => $generalAccount->id,
            'branch_id' => null
        ]);

        // 3. توجيه خاص لفرع رقم 10
        AccountMapping::create([
            'key' => 'cash_box',
            'name' => 'Cash',
            'account_id' => $branchAccount->id,
            'branch_id' => 10
        ]);

        // الاختبار A: طلب التوجيه لفرع 10 (يجب أن يأخذ الخاص)
        $this->assertEquals(
            $branchAccount->id,
            $this->service->getAccountId('cash_box', 10)
        );

        // الاختبار B: طلب التوجيه لفرع 99 (ليس له خاص، يجب أن يأخذ العام)
        $this->assertEquals(
            $generalAccount->id,
            $this->service->getAccountId('cash_box', 99)
        );
    }

    #[Test]
    public function api_can_update_mapping()
    {
        $this->actingAsAccountant();

        // إعداد بيانات أولية
        $accountOld = Account::factory()->create();
        $accountNew = Account::factory()->create();

        $mapping = AccountMapping::create([
            'key' => 'test_key',
            'name' => 'Test',
            'account_id' => $accountOld->id
        ]);

        // محاولة التحديث عبر الـ API
        $response = $this->putJson("/api/v1/accounting/account-mappings/{$mapping->id}", [
            'account_id' => $accountNew->id
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.account_id', $accountNew->id);

        $this->assertDatabaseHas('account_mappings', [
            'id' => $mapping->id,
            'account_id' => $accountNew->id
        ]);
    }
}
