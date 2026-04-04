<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->currencyCode(),
            'name' => $this->faker->currencyCode(),
            'symbol' => '$',
            'exchange_rate' => 1.000000,
            'is_base' => false,
            'is_active' => true,
        ];
    }
}
