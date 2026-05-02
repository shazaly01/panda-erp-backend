<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\CalendarException;
use App\Modules\HR\Http\Requests\Schedules\CalendarExceptionRequest;
use App\Modules\HR\Http\Resources\CalendarExceptionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class CalendarExceptionController extends Controller
{
    /**
     * عرض قائمة الاستثناءات التقويمية والطوارئ
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CalendarException::class);

        $exceptions = CalendarException::latest('start_date')->get();

        return CalendarExceptionResource::collection($exceptions);
    }

    /**
     * إنشاء استثناء تقويمي أو حالة طوارئ جديدة
     */
    public function store(CalendarExceptionRequest $request): CalendarExceptionResource
    {
        $this->authorize('create', CalendarException::class);

        $exception = CalendarException::create($request->validated());

        return new CalendarExceptionResource($exception);
    }

    /**
     * عرض تفاصيل استثناء محدد
     */
    public function show(CalendarException $calendarException): CalendarExceptionResource
    {
        $this->authorize('view', $calendarException);

        return new CalendarExceptionResource($calendarException);
    }

    /**
     * تحديث بيانات الاستثناء
     */
    public function update(CalendarExceptionRequest $request, CalendarException $calendarException): CalendarExceptionResource
    {
        $this->authorize('update', $calendarException);

        $calendarException->update($request->validated());

        return new CalendarExceptionResource($calendarException);
    }

    /**
     * حذف الاستثناء
     */
    public function destroy(CalendarException $calendarException): JsonResponse
    {
        $this->authorize('delete', $calendarException);

        $calendarException->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'تم حذف الاستثناء التقويمي بنجاح.'
        ]);
    }
}
