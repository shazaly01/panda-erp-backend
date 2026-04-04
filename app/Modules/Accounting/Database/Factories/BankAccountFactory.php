<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'bank_name' => $this->faker->company(),
            'account_name' => $this->faker->name(),
            'account_number' => $this->faker->bankAccountNumber(),
            'iban' => $this->faker->iban(),
            'currency_id' => Currency::factory(),
            'is_active' => true,
            'account_id' => null,
        ];
    }
}
