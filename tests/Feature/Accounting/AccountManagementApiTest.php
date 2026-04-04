<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class AccountManagementApiTest extends ApiTestCase
{
    private string $endpoint = '/api/v1/accounting/accounts';

    #[Test]
    public function accountant_can_view_accounts_tree_structure()
    {
        $this->actingAsAccountant();

        // 1. إنشاء هيكلية: أب -> ابن
        $parent = Account::factory()->create([
            'code' => '100',
            'name' => 'Assets'
        ]);

        $child = Account::factory()->create([
            'code' => '100001',
            'parent_id' => $parent->id,
            'name' => 'Cash'
        ]);

        // 2. طلب العرض
        $response = $this->getJson($this->endpoint);

        // 3. التحقق: نتوقع أن نرى الأب، وداخله مصفوفة children تحتوي الابن
        $response->assertOk()
                 ->assertJsonPath('data.0.id', $parent->id)
                 ->assertJsonPath('data.0.children.0.id', $child->id);
    }

    #[Test]
    public function accountant_can_update_account_details()
    {
        $this->actingAsAccountant();

        $account = Account::factory()->create([
            'name' => 'Old Name',
            'description' => 'Old Desc'
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'New Description',
            // يجب إرسال الحقول الإلزامية الأخرى حسب قواعد الـ Validation الخاصة بك
            'code' => $account->code, // نفس الكود
            'nature' => $account->nature->value,
            'type' => $account->type,
        ];

        $response = $this->putJson("{$this->endpoint}/{$account->id}", $updateData);

        $response->assertOk();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Updated Name',
            'description' => 'New Description'
        ]);
    }

    #[Test]
    public function cannot_delete_account_that_has_children()
    {
        $this->actingAsAccountant();

        // 1. إنشاء أب وابن
        $parent = Account::factory()->create();
        $child = Account::factory()->create(['parent_id' => $parent->id]);

        // 2. محاولة حذف الأب
        $response = $this->deleteJson("{$this->endpoint}/{$parent->id}");

        // 3. التوقع: فشل العملية (422 Unprocessable Entity) حسب منطق الـ Service
        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['account']); // الرسالة تأتي تحت مفتاح account

        // التأكد من أن الأب لا يزال موجوداً في القاعدة
        $this->assertDatabaseHas('accounts', ['id' => $parent->id]);
    }

    #[Test]
    public function can_delete_leaf_account()
    {
        $this->actingAsAccountant();

        // حساب وحيد ليس له أبناء ولا قيود
        $account = Account::factory()->create();

        $response = $this->deleteJson("{$this->endpoint}/{$account->id}");

        $response->assertOk(); // أو assertNoContent() حسب استجابة الكونترولر

        // التأكد من حذفه (Soft Delete)
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }
}
