<?php
//tests\Feature\Accounting\AccountTest.php
namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;
use PHPUnit\Framework\Attributes\Test;

class AccountTest extends ApiTestCase
{
    #[Test]
    public function unauthenticated_users_cannot_view_accounts(): void
    {
        // محاولة الدخول بدون توكن
        $response = $this->getJson('/api/v1/accounts');

        $response->assertUnauthorized(); // 401
    }

    #[Test]
    public function an_accountant_can_create_a_root_account(): void
    {
        // 1. التجهيز: الدخول كمحاسب
        $this->actingAsAccountant();

        $data = [
            'code' => '1000',
            'name' => 'الأصول',
            'nature' => AccountNature::Debit->value, // استخدام القيمة من الـ Enum
            'type' => 'asset',
            'is_transactional' => false, // حساب رئيسي
            'requires_cost_center' => false,
        ];

        // 2. الفعل: إرسال طلب الإنشاء
        $response = $this->postJson('/api/v1/accounts', $data);

        // 3. التحقق: تم الإنشاء والبيانات موجودة في القاعدة
        $response->assertCreated(); // 201
        $this->assertDatabaseHas('accounts', [
            'code' => '1000',
            'name' => 'الأصول'
        ]);
    }

    #[Test]
    public function cannot_create_account_with_duplicate_code(): void
    {
        $this->actingAsAccountant();

        // ننشئ حساباً أولاً باستخدام الـ Factory
        Account::factory()->create(['code' => '1234']);

        // نحاول إنشاء حساب آخر بنفس الكود
        $response = $this->postJson('/api/v1/accounts', [
            'code' => '1234', // مكرر
            'name' => 'حساب آخر',
            'nature' => AccountNature::Debit->value,
            'type' => 'asset',
        ]);

        // نتوقع خطأ في التحقق (Validation Error)
        $response->assertUnprocessable(); // 422
        $response->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function an_accountant_can_create_child_account(): void
    {
        $this->actingAsAccountant();

        // ننشئ الأب أولاً
        $parent = Account::factory()->create(['code' => '1000', 'is_transactional' => false]);

        $data = [
            'code' => '1001',
            'name' => 'نقدية بالصندوق',
            'nature' => AccountNature::Debit->value,
            'type' => 'asset',
            'parent_id' => $parent->id, // نربطه بالأب
            'is_transactional' => true,
        ];

        $response = $this->postJson('/api/v1/accounts', $data);

        $response->assertCreated();

        // التحقق من أن الابن تم ربطه بالأب (Nested Set Logic)
        $child = Account::where('code', '1001')->first();
        $this->assertEquals($parent->id, $child->parent_id);
    }

    #[Test]
    public function cannot_delete_account_with_children(): void
    {
        $this->actingAsAccountant();

        // ننشئ أب وابن
        $parent = Account::factory()->create();
        $child = Account::factory()->create(['parent_id' => $parent->id]);

        // محاولة حذف الأب
        $response = $this->deleteJson("/api/v1/accounts/{$parent->id}");

        // نتوقع 409 Conflict (لأننا وضعنا هذا الشرط في الموديل)
        $response->assertStatus(409);

        // نتأكد أن الأب لم يحذف من القاعدة
        $this->assertDatabaseHas('accounts', ['id' => $parent->id]);
    }
}
