<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Services\TreasuryService;
use PHPUnit\Framework\Attributes\Test;

class TreasuryIntegrationTest extends ApiTestCase
{
    protected TreasuryService $treasuryService;
    protected Account $assetsAccount;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // استدعاء السيرفس من الحاوية (Service Container)
        $this->treasuryService = app(TreasuryService::class);

        // 1. تجهيز العملة
        $this->currency = Currency::factory()->create(['code' => 'SAR', 'is_base' => true]);

        // 2. تجهيز شجرة الحسابات (أصول -> نقدية)
        $root = Account::factory()->create(['code' => '1', 'name' => 'الأصول']);
        $this->assetsAccount = Account::factory()->create([
            'code' => '11',
            'parent_id' => $root->id,
            'name' => 'النقدية وما في حكمها'
        ]);

        // 3. ضبط التوجيه المحاسبي (Mapping)
        // نخبر النظام أن الأب الافتراضي للخزائن هو حساب "النقدية"
        AccountMapping::create([
            'key' => 'default_box_parent',
            'account_id' => $this->assetsAccount->id
        ]);

        // نخبر النظام أن الأب الافتراضي للبنوك هو حساب "النقدية" أيضاً
        AccountMapping::create([
            'key' => 'default_bank_parent',
            'account_id' => $this->assetsAccount->id
        ]);
    }

    #[Test]
    public function creating_a_box_automatically_creates_a_gl_account()
    {
        // البيانات القادمة من الفورم
        $data = [
            'name' => 'الخزينة الرئيسية',
            'currency_id' => $this->currency->id,
            'branch_id' => 1,
            'description' => 'خزينة الفرع الرئيسي',
            'is_active' => true
        ];

        // تنفيذ العملية
        $box = $this->treasuryService->createBox($data);

        // التحقق 1: الخزينة تم إنشاؤها
        $this->assertDatabaseHas('boxes', ['name' => 'الخزينة الرئيسية']);

        // التحقق 2: تم إنشاء حساب مالي مرتبط
        $this->assertNotNull($box->account_id, 'Box should have an account_id');

        $account = Account::find($box->account_id);
        $this->assertEquals('الخزينة الرئيسية', $account->name); // الاسم مطابق
        $this->assertEquals($this->assetsAccount->id, $account->parent_id); // الأب صحيح
        $this->assertEquals($this->currency->id, $account->currency_id); // العملة مطابقة
        $this->assertEquals('11001', $account->code); // الكود تم توليده تلقائياً (11 + 01)
    }

    #[Test]
    public function creating_a_bank_account_automatically_creates_a_gl_account()
    {
        $data = [
            'bank_name' => 'بنك الخرطوم',
            'account_name' => 'شركة باندا',
            'account_number' => '123456789',
            'iban' => 'SD0000123456789',
            'currency_id' => $this->currency->id,
            'branch_id' => 1,
            'is_active' => true
        ];

        $bankAccount = $this->treasuryService->createBankAccount($data);

        // التحقق: الحساب المالي يجب أن يحمل اسم البنك ورقم الحساب
        $expectedName = 'بنك الخرطوم - 123456789';

        $this->assertDatabaseHas('accounts', [
            'id' => $bankAccount->account_id,
            'name' => $expectedName,
            'parent_id' => $this->assetsAccount->id
        ]);
    }

    #[Test]
    public function updating_box_name_updates_gl_account_name()
    {
        // 1. إنشاء خزينة
        $box = $this->treasuryService->createBox([
            'name' => 'Old Name',
            'currency_id' => $this->currency->id,
        ]);

        // 2. تحديث الاسم
        $this->treasuryService->updateBox($box, [
            'name' => 'New Updated Name',
            'currency_id' => $this->currency->id, // يجب إرسال الحقول المطلوبة
            'is_active' => true
        ]);

        // 3. التحقق من تحديث الحساب المالي
        $this->assertDatabaseHas('accounts', [
            'id' => $box->account_id,
            'name' => 'New Updated Name' // يجب أن يتغير الاسم هنا أيضاً
        ]);
    }
}
