<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Services\FiscalYearService;
use App\Modules\Accounting\Http\Requests\StoreFiscalYearRequest;
use App\Modules\Accounting\Http\Requests\UpdateFiscalYearRequest;
use App\Modules\Accounting\Http\Resources\FiscalYearResource;

class FiscalYearController extends Controller
{
    public function __construct(
        protected FiscalYearService $service
    ) {}

    public function index()
    {
        $this->authorize('viewAny', FiscalYear::class);

        $years = FiscalYear::latest('start_date')->get();

        return FiscalYearResource::collection($years);
    }

    public function show(FiscalYear $fiscalYear)
    {
        $this->authorize('view', $fiscalYear);
        return new FiscalYearResource($fiscalYear);
    }

    public function store(StoreFiscalYearRequest $request)
    {
        $year = $this->service->createFiscalYear($request->validated());

        return response()->json([
            'message' => 'تم إنشاء السنة المالية بنجاح',
            'data' => new FiscalYearResource($year),
        ], 201);
    }

    public function update(UpdateFiscalYearRequest $request, FiscalYear $fiscalYear)
    {
        $updatedYear = $this->service->updateFiscalYear($fiscalYear, $request->validated());

        return response()->json([
            'message' => 'تم تحديث السنة المالية بنجاح',
            'data' => new FiscalYearResource($updatedYear),
        ]);
    }

    public function destroy(FiscalYear $fiscalYear)
    {
        $this->authorize('delete', $fiscalYear);
        $this->service->deleteFiscalYear($fiscalYear);

        return response()->json([
            'message' => 'تم حذف السنة المالية بنجاح',
        ]);
    }
}
