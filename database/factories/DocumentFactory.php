<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word() . ' Document',
            // لا ننشئ ملفًا فعليًا هنا، فقط مسار وهمي
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            // ربطه بشركة موجودة أو إنشاء واحدة جديدة
            'company_id' => Company::factory(),
        ];
    }
}
