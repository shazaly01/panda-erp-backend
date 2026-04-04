<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition(): array
    {
        $year = 2025; // سنة ثابتة للاختبارات
        return [
            'name' => "Fiscal Year $year",
            'start_date' => Carbon::create($year, 1, 1),
            'end_date' => Carbon::create($year, 12, 31),
            'status' => FiscalYearStatus::Open,
        ];
    }
}
