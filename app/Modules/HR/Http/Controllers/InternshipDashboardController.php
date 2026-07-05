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
     * استعراض كافة طلبات التدريب الخارجية المعلقة
     */
    public function index(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InternshipApplication::class);

        // اقرأ الحالة المرسلة من الواجهة الأمامية، وإذا لم ترسل اجعل الافتراضي pending
        $status = $request->query('status', 'pending');

        $applications = InternshipApplication::where('status', $status)
            ->latest()
            ->paginate(15);

        return InternshipApplicationResource::collection($applications);
    }

    /**
     * استعراض قائمة المتدربين الحاليين النشطين في المؤسسة (باستخدام الـ Scope العازل الآمن)
     */
    public function activeInterns(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Employee::class);

        $interns = Employee::onlyInterns()
            ->with(['department', 'position', 'manager', 'profilePhoto'])
            ->latest()
            ->paginate(15);

        return EmployeeResource::collection($interns);
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
