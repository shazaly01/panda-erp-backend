<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Enums\CostCenterType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CostCenterFactory extends Factory
{
    protected $model = CostCenter::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('CC-###'),
            'name' => $this->faker->city() . ' Branch',
            'type' => $this->faker->randomElement(CostCenterType::cases()),
            'is_active' => true,
            'parent_id' => null,
        ];
    }
}
