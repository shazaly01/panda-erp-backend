<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoxFactory extends Factory
{
    protected $model = Box::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true) . ' Box',
            'currency_id' => Currency::factory(), // ينشئ عملة تلقائياً
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'account_id' => null, // سيتم تعبئته بواسطة السيرفس
        ];
    }
}
