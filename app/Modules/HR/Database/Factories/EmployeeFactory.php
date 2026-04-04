<?php

declare(strict_types=1);

namespace App\Modules\HR\Database\Factories;

use App\Modules\HR\Models\Employee;
use App\Modules\HR\Enums\Gender;
use App\Modules\HR\Enums\MaritalStatus;
use App\Modules\HR\Enums\EmployeeStatus;
use App\Modules\HR\Enums\EmploymentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'date_of_birth' => $this->faker->date('Y-m-d', '-20 years'), // عمره فوق 20
            'gender' => $this->faker->randomElement(Gender::cases()),
            'marital_status' => $this->faker->randomElement(MaritalStatus::cases()),
            'national_id' => $this->faker->unique()->numerify('##########'),

            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),

            'employee_number' => 'EMP-' . $this->faker->unique()->numerify('####'),
            'join_date' => $this->faker->date(),
            'status' => EmployeeStatus::Active,
            'employment_type' => EmploymentType::FullTime,

            // سننشئ مستخدماً مرتبطاً بهذا الموظف تلقائياً
            'user_id' => User::factory(),

            // نترك الإدارة والوظيفة فارغة حالياً (أو يمكنك إنشاء مصانع لها لاحقاً)
            'department_id' => null,
            'position_id' => null,
            'manager_id' => null,
        ];
    }
}
