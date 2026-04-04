<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tax_number' => fake()->unique()->numerify('###########'), // 11 رقمًا
            'address' => fake()->address(),
            'owner_name' => fake()->name(),
        ];
    }
}
