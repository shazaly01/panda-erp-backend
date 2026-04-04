<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;

class AccountCreationLogicTest extends ApiTestCase
{
    protected AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // استدعاء الخدمة الحقيقية من الـ Container
        $this->service = app(AccountService::class);
    }

    #[Test]
    public function it_calculates_account_level_automatically_based_on_parent()
    {
        // 1. إنشاء حساب أب (المستوى الافتراضي 1)
        $parent = Account::factory()->create(['level' => 1]);

        // 2. تجهيز بيانات الابن
        $childData = [
            'code' => '1001',
            'name' => 'Cash Account',
            'nature' => AccountNature::Debit,
            'type' => 'asset',
            'parent_id' => $parent->id,
            'is_transactional' => true,
        ];

        // 3. تنفيذ الإنشاء عبر الخدمة
        $child = $this->service->createAccount($childData);

        // 4. التحقق: يجب أن يكون مستوى الابن = مستوى الأب + 1
        $this->assertEquals(2, $child->level);
        $this->assertEquals($parent->id, $child->parent_id);
    }

    #[Test]
    public function service_prevents_duplicate_code_creation()
    {
        // إنشاء حساب موجود مسبقاً في قاعدة البيانات
        Account::factory()->create(['code' => '5000']);

        // نتوقع حدوث ValidationException
        $this->expectException(ValidationException::class);

        // محاولة إنشاء حساب بنفس الكود عبر الخدمة
        $this->service->createAccount([
            'code' => '5000', // مكرر
            'name' => 'Duplicate Account',
            'nature' => AccountNature::Credit,
            'type' => 'liability',
        ]);
    }

    #[Test]
    public function generate_code_creates_correct_first_child_suffix()
    {
        // الأب كوده 101
        $parent = Account::factory()->create(['code' => '101']);

        // التوقع: أول ابن يجب أن يكون 101 + 001 = 101001
        $generatedCode = $this->service->generateCode($parent->id);

        $this->assertEquals('101001', $generatedCode);
    }

    #[Test]
    public function generate_code_increments_sequence_correctly()
    {
        // الأب كوده 200
        $parent = Account::factory()->create(['code' => '200']);

        // محاكاة وجود ابن سابق كوده 200005
        Account::factory()->create([
            'parent_id' => $parent->id,
            'code' => '200005'
        ]);

        // التوقع: الابن القادم يجب أن يكون 200006
        $generatedCode = $this->service->generateCode($parent->id);

        $this->assertEquals('200006', $generatedCode);
    }

    #[Test]
    public function generate_code_handles_padding_boundaries()
    {
        // اختبار الحالة الحدية: الانتقال من 009 إلى 010
        $parent = Account::factory()->create(['code' => '300']);

        // وجود ابن ينتهي بـ 9
        Account::factory()->create([
            'parent_id' => $parent->id,
            'code' => '300009'
        ]);

        $generatedCode = $this->service->generateCode($parent->id);

        // يجب أن يحافظ على الأصفار (Padding) ولا يتحول إلى 30010 بل 300010
        $this->assertEquals('300010', $generatedCode);
    }
}
