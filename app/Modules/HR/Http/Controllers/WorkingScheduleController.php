<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\WorkingSchedule;
use App\Modules\HR\Services\WorkingScheduleService;
use App\Modules\HR\Http\Requests\Schedules\WorkingScheduleRequest;
use App\Modules\HR\Http\Resources\WorkingScheduleResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class WorkingScheduleController extends Controller
{
    private WorkingScheduleService $workingScheduleService;

    public function __construct(WorkingScheduleService $workingScheduleService)
    {
        $this->workingScheduleService = $workingScheduleService;
    }

    /**
     * عرض قائمة القوالب
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkingSchedule::class);

        $schedules = WorkingSchedule::with('lines.shift')->latest()->get();

        return WorkingScheduleResource::collection($schedules);
    }

    /**
     * إنشاء قالب جديد
     */
    public function store(WorkingScheduleRequest $request): WorkingScheduleResource
    {
        $this->authorize('create', WorkingSchedule::class);

        $schedule = $this->workingScheduleService->createSchedule($request->validated());

        return new WorkingScheduleResource($schedule);
    }

    /**
     * عرض تفاصيل قالب محدد
     */
    public function show(WorkingSchedule $workingSchedule): WorkingScheduleResource
    {
        $this->authorize('view', $workingSchedule);

        $workingSchedule->load('lines.shift');

        return new WorkingScheduleResource($workingSchedule);
    }

    /**
     * تحديث بيانات قالب
     */
    public function update(WorkingScheduleRequest $request, WorkingSchedule $workingSchedule): WorkingScheduleResource
    {
        $this->authorize('update', $workingSchedule);

        $updatedSchedule = $this->workingScheduleService->updateSchedule($workingSchedule, $request->validated());

        return new WorkingScheduleResource($updatedSchedule);
    }

    /**
     * حذف قالب
     */
    public function destroy(WorkingSchedule $workingSchedule): JsonResponse
    {
        $this->authorize('delete', $workingSchedule);

        $this->workingScheduleService->deleteSchedule($workingSchedule);

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حذف القالب بنجاح.'
        ]);
    }
}
