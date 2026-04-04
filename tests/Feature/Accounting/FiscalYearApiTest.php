<?php

namespace Tests\Feature\Accounting;

use Tests\ApiTestCase;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class FiscalYearApiTest extends ApiTestCase
{
    private string $endpoint = '/api/v1/accounting/fiscal-years';

    #[Test]
    public function accountant_can_create_fiscal_year()
    {
        $this->actingAsAccountant();

        $payload = [
            'name' => 'السنة المالية 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => FiscalYearStatus::Open->value,
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertCreated()
                 ->assertJsonPath('data.name', 'السنة المالية 2026')
                 ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('fiscal_years', [
            'name' => 'السنة المالية 2026',
            'created_by' => $this->accountantUser->id // التأكد من حفظ المستخدم
        ]);
    }

    #[Test]
    public function validation_fails_if_end_date_is_before_start_date()
    {
        $this->actingAsAccountant();

        $payload = [
            'name' => 'سنة خاطئة',
            'start_date' => '2026-12-31',
            'end_date' => '2026-01-01', // خطأ منطقي
            'status' => FiscalYearStatus::Open->value,
        ];

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function accountant_can_close_fiscal_year()
    {
        $this->actingAsAccountant();

        $year = FiscalYear::factory()->create([
            'status' => FiscalYearStatus::Open
        ]);

        // إرسال طلب تحديث لإغلاق السنة
        $response = $this->putJson("{$this->endpoint}/{$year->id}", [
            'status' => FiscalYearStatus::Closed->value,
            'name' => $year->name,
            'start_date' => $year->start_date->format('Y-m-d'),
            'end_date' => $year->end_date->format('Y-m-d'),
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('fiscal_years', [
            'id' => $year->id,
            'status' => 'closed'
        ]);
    }

    #[Test]
    public function unauthorized_users_cannot_manage_years()
    {
        // Guest
        $this->getJson($this->endpoint)->assertUnauthorized();

        // Non-Accountant User
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson($this->endpoint, [])->assertForbidden();
    }
}
