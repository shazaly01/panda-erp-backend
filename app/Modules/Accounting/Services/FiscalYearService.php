<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FiscalYearService
{
    public function createFiscalYear(array $data): FiscalYear
    {
        return DB::transaction(function () use ($data) {
            return FiscalYear::create([
                ...$data,
                'created_by' => Auth::id(),
            ]);
        });
    }

    public function updateFiscalYear(FiscalYear $fiscalYear, array $data): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $data) {
            $fiscalYear->update($data);
            return $fiscalYear->refresh();
        });
    }

    public function deleteFiscalYear(FiscalYear $fiscalYear): bool
    {
        return DB::transaction(function () use ($fiscalYear) {
            return $fiscalYear->delete();
        });
    }
}
