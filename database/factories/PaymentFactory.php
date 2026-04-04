<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 1000, 50000),

            // --- [التصحيح هنا] ---
            'payment_date' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            // --- [نهاية التصحيح] ---

            'notes' => fake()->sentence(),
            'project_id' => Project::factory(),
        ];
    }
}
