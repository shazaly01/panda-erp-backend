<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\VoucherDetail;
use App\Modules\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherDetailFactory extends Factory
{
    protected $model = VoucherDetail::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'amount' => 500,
            'description' => $this->faker->word(),
        ];
    }
}
