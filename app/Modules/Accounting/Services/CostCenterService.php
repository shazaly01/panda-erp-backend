<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CostCenter;
use Illuminate\Support\Facades\DB;

class CostCenterService
{
    public function createCostCenter(array $data): CostCenter
    {
        return DB::transaction(function () use ($data) {
            return CostCenter::create($data);
        });
    }

    public function updateCostCenter(CostCenter $costCenter, array $data): CostCenter
    {
        return DB::transaction(function () use ($costCenter, $data) {
            $costCenter->update($data);
            return $costCenter->refresh();
        });
    }

    public function deleteCostCenter(CostCenter $costCenter): bool
    {
        return DB::transaction(function () use ($costCenter) {
            return $costCenter->delete();
        });
    }
}
