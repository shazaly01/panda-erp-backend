<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Enums\CostCenterType;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class CostCenterApiTest extends ApiTestCase
{
    private string $endpoint = '/api/v1/accounting/cost-centers';

    #[Test]
    public function unauthorized_users_cannot_manage_cost_centers()
    {
        // 1. Guest
        $this->getJson($this->endpoint)->assertUnauthorized();

        // 2. Authenticated but not Accountant
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson($this->endpoint)->assertForbidden();
    }

    #[Test]
    public function accountant_can_create_valid_cost_center()
    {
        $this->actingAsAccountant();

        $payload = [
            'code' => 'CC-100',
            'name' => 'IT Department',
            'type' => CostCenterType::Department->value,
            'is_active' => true,
            // الحقول الجديدة من المايجريشن
            'is_branch' => true,
            'code_prefix' => 'IT-DXB',
            'notes' => 'المركز الرئيسي لتقنية المعلومات',
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertCreated()
                 ->assertJsonPath('data.code', 'CC-100')
                 ->assertJsonPath('data.name', 'IT Department');

        $this->assertDatabaseHas('cost_centers', [
            'code' => 'CC-100',
            'type' => 'department',
            'is_branch' => 1, // التأكد من حفظ الحقل الجديد
            'code_prefix' => 'IT-DXB'
        ]);
    }

    #[Test]
    public function it_validates_enum_type_correctly()
    {
        $this->actingAsAccountant();

        $payload = [
            'code' => 'CC-999',
            'name' => 'Invalid Type Center',
            'type' => 'unknown_type', // قيمة غير موجودة في الـ Enum
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['type']);
    }

    #[Test]
    public function accountant_can_view_cost_centers_tree()
    {
        $this->actingAsAccountant();

        // 1. إنشاء أب
        $parent = CostCenter::factory()->create([
            'code' => 'MAIN',
            'name' => 'Main Center'
        ]);

        // 2. إنشاء ابن مرتبط بالأب
        $child = CostCenter::factory()->create([
            'code' => 'SUB-1',
            'parent_id' => $parent->id,
            'name' => 'Sub Center'
        ]);

        // 3. طلب العرض
        $response = $this->getJson($this->endpoint);

        $response->assertOk();

        // البحث عن الأب والتأكد أن الابن بداخله
        // ملاحظة: نستخدم loops أو json path للبحث لأن الترتيب قد يختلف
        $data = $response->json('data');
        $parentInResponse = collect($data)->firstWhere('id', $parent->id);

        $this->assertNotNull($parentInResponse, 'Parent not found in response');
        $this->assertEquals($child->id, $parentInResponse['children'][0]['id']);
    }

    #[Test]
    public function cannot_delete_cost_center_with_children()
    {
        $this->actingAsAccountant();

        $parent = CostCenter::factory()->create();
        $child = CostCenter::factory()->create(['parent_id' => $parent->id]);

        $response = $this->deleteJson("{$this->endpoint}/{$parent->id}");

        // الموديل يستخدم abort(409, ...) لذا نتوقع 409 Conflict
        $response->assertStatus(409);

        $this->assertDatabaseHas('cost_centers', ['id' => $parent->id]);
    }

    #[Test]
    public function can_update_cost_center()
    {
        $this->actingAsAccountant();

        $center = CostCenter::factory()->create([
            'name' => 'Old Name'
        ]);

        $payload = [
            'code' => $center->code,
            'name' => 'New Updated Name',
            'type' => $center->type->value,
            'is_branch' => false
        ];

        $response = $this->putJson("{$this->endpoint}/{$center->id}", $payload);

        $response->assertOk()
                 ->assertJsonPath('data.name', 'New Updated Name');

        $this->assertDatabaseHas('cost_centers', [
            'id' => $center->id,
            'name' => 'New Updated Name'
        ]);
    }
}
