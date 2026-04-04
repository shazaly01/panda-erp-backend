<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Project ' . fake()->words(3, true),
            'contract_value' => fake()->randomFloat(2, 10000, 1000000),

            // --- [التصحيح هنا] ---
            // تحويل كائن التاريخ إلى صيغة نصية بسيطة (Y-m-d)
            // لضمان أن التحقق من الصحة (Validation) سيتعرف عليه كتاريخ صالح.
            'award_date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            // --- [نهاية التصحيح] ---

            // ربطه بشركة موجودة أو إنشاء واحدة جديدة تلقائيًا
            'company_id' => Company::factory(),
        ];
    }
}
