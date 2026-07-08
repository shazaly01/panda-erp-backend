<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\InternshipApplication;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Requests\Employee\ApproveInternApplicationRequest;
use App\Modules\HR\Http\Requests\Employee\ConvertInternToFullTimeRequest;
use App\Modules\HR\Http\Requests\Employee\RejectInternApplicationRequest;
use App\Modules\HR\Http\Resources\InternshipApplicationResource;
use App\Modules\HR\Http\Resources\EmployeeResource;
use App\Modules\HR\Services\InternshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InternshipDashboardController extends Controller
{
    protected InternshipService $internshipService;

    public function __construct(InternshipService $internshipService)
    {
        $this->internshipService = $internshipService;
    }

    /**
     * استعراض كافة طلبات التدريب الخارجية المعلقة والمرفوضة مع الفلترة المتقدمة والتاريخ
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InternshipApplication::class);

        $status = $request->query('status', 'pending');

        $applications = $this->internshipService->getApplicationsWithFilters($status, $request->all());

        return InternshipApplicationResource::collection($applications);
    }

    /**
     * استعراض قائمة المتدربين الحاليين النشطين في المؤسسة مع دعم البحث والتواريخ
     */
    public function activeInterns(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Employee::class);

        $interns = $this->internshipService->getActiveInternsWithFilters($request->all());

        return EmployeeResource::collection($interns);
    }

    /**
     * استعراض قائمة المتدربين المنتهية فترتهم التدريبية ولم يتم تثبيتهم بعد
     */
    public function completedInterns(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Employee::class);

        $interns = $this->internshipService->getCompletedInternsWithFilters($request->all());

        return EmployeeResource::collection($interns);
    }

    /**
     * تمديد فترة التدريب وتعديل تواريخ البدء والانتهاء للمتدربين الحاليين أو المنتهية فترتهم
     */
    public function updateDates(Request $request, int $id): JsonResponse
    {
        // 1. جلب كائن المتدرب أولاً عبر آلية كسر العزل الآمنة لتمريره إلى الـ Policy ومنع خطأ الـ Arguments
        $employeeInstance = Employee::withInterns()->findOrFail($id);

        // 2. التحقق من الصلاحيات الإدارية بتمرير الكائن الحقيقي
        Gate::authorize('update', $employeeInstance);

        // 3. التحقق الصارم من صحة التواريخ المدخلة بواسطة المشرف
        $validated = $request->validate([
            'internship_start_date' => 'required|date',
            'internship_end_date' => 'required|date|after_or_equal:internship_start_date',
        ], [
            'internship_start_date.required' => 'تاريخ بدء التدريب مطلوب.',
            'internship_end_date.required' => 'تاريخ انتهاء التدريب مطلوب.',
            'internship_end_date.after_or_equal' => 'تاريخ انتهاء التدريب يجب أن يكون مساوياً أو بعد تاريخ البدء.',
        ]);

        // 4. استدعاء الخدمة لتحديث السجلات ومزامنة العقود بصورة معزولة وآمنة
        $employee = $this->internshipService->updateInternshipDates($id, $validated);

        return response()->json([
            'message' => 'تم تحديث وتمديد فترات التدريب الأكاديمي بنجاح، ومزامنة العقد المالي التابع له.',
            'data' => new EmployeeResource($employee->load(['department', 'position', 'currentContract']))
        ], 200);
    }

    /**
     * اعتماد وقبول طلب التدريب وتحويله لمتدرب رسمي
     */
    public function approve(ApproveInternApplicationRequest $request, int $id): JsonResponse
    {
        $application = InternshipApplication::findOrFail($id);
        Gate::authorize('approve', $application);

        $employee = $this->internshipService->approveApplication($id, $request->validated());

        return response()->json([
            'message' => 'تم قبول المتدرب بنجاح، وإصدار الرقم الوظيفي والباركود الخاص به، وتوليد عقده آلياً.',
            'data' => new EmployeeResource($employee->load(['department', 'position']))
        ], 200);
    }

    /**
     * رفض طلب التدريب المقدم (محقون بطبقة الفحص المستحدثة ومعزول تماماً داخل الـ Service)
     */
    public function reject(RejectInternApplicationRequest $request, int $id): JsonResponse
    {
        $application = InternshipApplication::findOrFail($id);
        Gate::authorize('reject', $application);

        $this->internshipService->rejectApplication($id);

        return response()->json([
            'message' => 'تم رفض طلب التدريب بنجاح.'
        ], 200);
    }

    /**
     * محرك التثبيت النهائي (Conversion) لتحويل المتدرب إلى موظف دائم رسمي
     */
    public function convert(ConvertInternToFullTimeRequest $request, int $id): JsonResponse
    {
        $intern = Employee::onlyInterns()->findOrFail($id);
        Gate::authorize('convert', $intern);

        $employee = $this->internshipService->convertToFullTime($id, $request->validated());

        return response()->json([
            'message' => 'تهانينا! تم تثبيت المتدرب بنجاح كموظف دائم، وإصدار رقمه الوظيفي الرسمي وعقد العمل الجديد.',
            'data' => new EmployeeResource($employee->load(['department', 'position', 'currentContract']))
        ], 200);
    }
}
