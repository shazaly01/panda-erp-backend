<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        return [
            'branch_id' => CostCenter::factory(),
            'type' => VoucherType::Payment,

            // --- التعديل هنا ---
            // نضع رقماً وهمياً عشوائياً لتجاوز قيد NOT NULL في قاعدة البيانات
            'number' => 'TEST-' . $this->faker->unique()->randomNumber(6),
            // -------------------

            'date' => now(),
            'description' => $this->faker->sentence(),
            'currency_id' => Currency::factory(),
            'exchange_rate' => 1,
            'amount' => 1000,
            'status' => VoucherStatus::Draft,
            'created_by' => 1,
        ];
    }
}
