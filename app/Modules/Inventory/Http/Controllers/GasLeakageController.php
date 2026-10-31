<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\Serials\RecordGasLeakageRequest;
use App\Modules\Inventory\Services\GasLeakageService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class GasLeakageController extends Controller
{
    public function __construct(
        protected readonly GasLeakageService $gasLeakageService
    ) {}

    /**
     * تسجيل فاقد أو تسريب وزني لأسطوانة غاز وتوليد حركة الخصم المخزني
     */
    public function __invoke(RecordGasLeakageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->gasLeakageService->recordLeakage(
                serialId: (int) $validated['serial_id'],
                newGrossWeight: (float) $validated['new_gross_weight'],
                warehouseId: isset($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
                reason: $validated['reason'] ?? null,
                userId: (int) $request->user()?->id
            );

            return response()->json([
                'message' => 'تم إثبات الفاقد والتسريب بنجاح، وترحيل حركة الخصم المخزني.',
                'data' => $result,
            ], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}