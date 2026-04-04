<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;
use Laravel\Sanctum\Sanctum; // تأكد من وجود هذا السطر
use PHPUnit\Framework\Attributes\Test;

class CreateAccountApiTest extends ApiTestCase
{
    // الرابط الصحيح الذي استخرجناه سابقاً
    private string $endpoint = '/api/v1/accounting/accounts';

    #[Test]
    public function unauthorized_users_cannot_create_accounts()
    {
        $data = Account::factory()->make()->toArray();

        // 1. محاولة ضيف (Guest) - لا يوجد توكن
        // النتيجة: 401 Unauthorized (الباب مغلق)
        $this->postJson($this->endpoint, $data)
             ->assertUnauthorized();

        // 2. محاولة مستخدم مسجل لكن ليس محاسب
        $user = \App\Models\User::factory()->create();

        // استخدام Sanctum::actingAs ضروري لأن الـ API يحتاج توكن
        Sanctum::actingAs($user);

        // النتيجة: 403 Forbidden (دخل الباب لكن ممنوع من التصرف)
        $this->postJson($this->endpoint, $data)
             ->assertForbidden();
    }

    #[Test]
    public function accountant_can_create_a_valid_account()
    {
        // استخدام الدالة المساعدة الموجودة في ApiTestCase
        $this->actingAsAccountant();

        $payload = [
            'code' => '1100',
            'name' => 'الأصول المتداولة',
            'nature' => AccountNature::Debit->value,
            'type' => 'asset',
            'is_transactional' => false,
            'requires_cost_center' => false,
            'description' => 'حساب رئيسي للأصول المتداولة',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertCreated()
                 ->assertJsonPath('data.code', '1100')
                 ->assertJsonPath('data.nature_label', 'مدين');

        $this->assertDatabaseHas('accounts', [
            'code' => '1100',
            'name' => 'الأصول المتداولة',
            'nature' => 'debit'
        ]);
    }

    #[Test]
    public function validation_fails_for_missing_required_fields()
    {
        $this->actingAsAccountant();

        $response = $this->postJson($this->endpoint, []);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['code', 'name', 'nature', 'type']);
    }

    #[Test]
    public function validation_fails_for_duplicate_code()
    {
        $this->actingAsAccountant();

        Account::factory()->create(['code' => '9999']);

        $payload = [
            'code' => '9999',
            'name' => 'New Name',
            'nature' => AccountNature::Credit->value,
            'type' => 'equity',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['code']);
    }

    #[Test]
    public function validation_fails_for_invalid_enum_value()
    {
        $this->actingAsAccountant();

        $payload = [
            'code' => '8888',
            'name' => 'Test Account',
            'nature' => 'invalid_nature',
            'type' => 'asset',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['nature']);
    }

    #[Test]
    public function it_validates_parent_id_must_exist()
    {
        $this->actingAsAccountant();

        $payload = [
            'code' => '7777',
            'name' => 'Sub Account',
            'nature' => AccountNature::Debit->value,
            'type' => 'asset',
            'parent_id' => 999999,
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['parent_id']);
    }
}
