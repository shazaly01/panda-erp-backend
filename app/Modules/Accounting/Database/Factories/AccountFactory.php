<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Enums\AccountNature;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    // مهم جداً: ربط المصنع بالموديل الموجود داخل الموديول
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            // توليد كود عشوائي فريد من 4 أرقام
            'code' => $this->faker->unique()->numerify('####'),
            'name' => $this->faker->company() . ' Account',
            'nature' => $this->faker->randomElement(AccountNature::cases()),
            'type' => 'asset',
            'is_transactional' => true,
            'requires_cost_center' => false,
            'is_active' => true,
            'parent_id' => null,
            'description' => $this->faker->sentence(),
        ];
    }
}
