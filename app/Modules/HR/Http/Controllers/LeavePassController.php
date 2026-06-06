<?php

declare(strict_types=1);

namespace App\Modules\HR\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\HR\Http\Requests\LeavePass\StoreLeavePassRequest;
use App\Modules\HR\Http\Requests\LeavePass\UpdateLeavePassRequest;
use App\Modules\HR\Http\Requests\LeavePass\ApproveLeavePassRequest;
use App\Modules\HR\Http\Requests\LeavePass\GateCheckLeavePassRequest;
use App\Modules\HR\Http\Resources\LeavePassResource;
use App\Modules\HR\Models\HrLeavePass;
use App\Modules\HR\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeavePassController extends Controller
{
    public function __construct()
    {
        // تطبيق حماية الـ Resource Actions تلقائيًا للمسارات القياسية (index, store, show, update, destroy)
        $this->authorizeResource(HrLeavePass::class, 'leave_pass');
    }

    /**
     * عرض قائمة أذونات الخروج المؤقت مع الفلترة بحسب الحالة أو التاريخ
     */
    public function index(Request $request)
    {
        $query = HrLeavePass::with(['employee', 'approvedBy', 'gateCheckedOutBy', 'gateCheckedInBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $passes = $query->latest()->paginate(15);
        return LeavePassResource::collection($passes);
    }

    /**
     * إصدار إذن خروج مؤقت مباشر من قبل المشرف المسؤول (موافقة فورية مدمجة)
     * يدعم الموظفين الذين لا يملكون حسابات دخول في المنظومة
     */
    public function store(StoreLeavePassRequest $request)
    {
        $validated = $request->validated();

        // التعرف على سجل الموظف الخاص بالمشرف الحالي الذي قام بتسجيل الدخول
        $approverEmployee = Employee::where('user_id', auth()->id())->first();

        $leavePass = DB::transaction(function () use ($validated, $approverEmployee) {
            // توليد كود رقمي فريد مكون من 8 خانات (يستخدم لإنتاج الـ QR والـ Barcode أو للبحث اليدوي)
            do {
                $passCode = (string) mt_rand(10000000, 99999999);
            } while (HrLeavePass::where('pass_code', $passCode)->exists());

            // الدمج المعماري: الإنشاء بحالة معتمدة فورًا وتوثيق هوية المشرف
            return HrLeavePass::create(array_merge($validated, [
                'pass_code'   => $passCode,
                'status'      => 'approved', // موافقة فورية مدمجة لتسهيل دورة العمل لعدم وجود حسابات للموظفين
                'approved_by' => $approverEmployee?->id,
            ]));
        });

        return new LeavePassResource($leavePass->load(['employee', 'approvedBy']));
    }

    /**
     * عرض تفاصيل إذن معين
     */
    public function show(HrLeavePass $leavePass)
    {
        return new LeavePassResource($leavePass->load(['employee', 'approvedBy', 'gateCheckedOutBy', 'gateCheckedInBy']));
    }

    /**
     * تعديل بيانات الإذن (متاح فقط طالما الإذن لم يتم استخدامه بعد للمرور الحركي)
     */
    public function update(UpdateLeavePassRequest $request, HrLeavePass $leavePass)
    {
        $validated = $request->validated();

        $leavePass->update($validated);

        return new LeavePassResource($leavePass->load('employee'));
    }

    /**
     * إلغاء أو حذف طلب الإذن مرناً
     */
    public function destroy(HrLeavePass $leavePass)
    {
        $leavePass->delete();
        return response()->noContent();
    }

    /**
     * قرار المشرف بالقبول أو الرفض الإداري لطلب الخروج (مسار احتياطي في حال تفعيل الخدمة الذاتية مستقبلاً)
     */
    public function approve(ApproveLeavePassRequest $request, HrLeavePass $leavePass)
    {
        // [إغلاق الثغرة الأمنية]: التحقق الصارم من امتلاك المشرف لصلاحية الاعتماد الإداري للأذونات
        $this->authorize('approve', HrLeavePass::class);

        $validated = $request->validated();
        $approverEmployee = Employee::where('user_id', auth()->id())->first();

        $leavePass->update([
            'status'      => $validated['status'],
            'approved_by' => $approverEmployee?->id,
        ]);

        return new LeavePassResource($leavePass->load(['employee', 'approvedBy']));
    }

    /**
     * الحركة الفعالة اليدوية للحرس عند البوابة الخارجية (الضغط اليدوي لتغيير الحالة بالواجهة الإدارية الخفيفة)
     */
    public function gateCheck(GateCheckLeavePassRequest $request, HrLeavePass $leavePass)
    {
        $this->authorize('gateCheck', $leavePass);
        $validated = $request->validated();

        $updatedPass = DB::transaction(function () use ($validated, $leavePass) {
            if ($validated['action'] === 'check_out') {
                if ($leavePass->status !== 'approved') {
                    abort(422, 'هذا الإذن غير معتمد إدارياً أو تم استخدامه مسبقاً.');
                }

                $leavePass->update([
                    'status'              => 'out',
                    'actual_leave_at'     => now(),
                    'gate_checked_out_by' => auth()->id(),
                ]);
            } elseif ($validated['action'] === 'check_in') {
                if ($leavePass->status !== 'out') {
                    abort(422, 'لا يمكن إثبات عودة موظف لم يتم إثبات خروجه الفعلي مسبقاً.');
                }

                $leavePass->update([
                    'status'             => 'returned',
                    'actual_return_at'   => now(),
                    'gate_checked_in_by' => auth()->id(),
                ]);
            }

            return $leavePass;
        });

        return new LeavePassResource($updatedPass->load(['employee', 'approvedBy', 'gateCheckedOutBy', 'gateCheckedInBy']));
    }

    /**
     * المسح الذكي المؤتمت لأفراد الأمن عند البوابة الخارجية (قراءة الـ QR Code من شاشة هاتف الموظف أو الورقة المطبوعة)
     */
    public function scanGateCode(Request $request)
    {
        $this->authorize('gateCheck', HrLeavePass::class);

        $request->validate([
            'pass_code' => 'required|string|exists:hr_leave_passes,pass_code',
        ], [
            'pass_code.exists' => 'رمز الـ QR الممسوح غير صحيح أو غير مسجل بنظام الأذونات الموحد.'
        ]);

        $leavePass = HrLeavePass::where('pass_code', $request->pass_code)->firstOrFail();

        $updatedPass = DB::transaction(function () use ($leavePass) {
            $now = now();

            // السيناريو الأول: الموظف يطلب المغادرة الفعلية الآن والطلب معتمد ومكتبي
            if ($leavePass->status === 'approved') {
                $leavePass->update([
                    'status'              => 'out',
                    'actual_leave_at'     => $now,
                    'gate_checked_out_by' => auth()->id(),
                ]);
                return $leavePass;
            }

            // السيناريو الثاني: الموظف عاد للمنشأة الآن ويريد إثبات الدخول وكسر حالة الخروج المؤقت لقفل الإذن
            if ($leavePass->status === 'out') {
                $leavePass->update([
                    'status'             => 'returned',
                    'actual_return_at'   => $now,
                    'gate_checked_in_by' => auth()->id(),
                ]);
                return $leavePass;
            }

            // سيناريو الحماية: إذا تم مسح الكود وهو مستخدم مسبقاً ومقفل أو مرفوض
            abort(422, 'تم إغلاق أو إلغاء هذا الإذن مسبقاً، لا يمكن استخدامه للمرور الحركي عبر البوابة.');
        });

        return new LeavePassResource($updatedPass->load(['employee', 'approvedBy', 'gateCheckedOutBy', 'gateCheckedInBy']));
    }

    /**
     * لوحة طوارئ الأمن والسلامة الصناعية (Muster Evacuation List)
     */
    public function emergencyMusterList(Request $request)
    {
        $this->authorize('viewAny', HrLeavePass::class);

        // جلب كافة الموظفين الذين هم على رأس عملهم اليوم
        $employees = Employee::where('status', 'active')
            ->with([
                'department:id,name',
                'position:id,name',
                'attendanceLogs' => function ($q) {
                    $q->where('date', now()->toDateString());
                },
                'leavePasses' => function ($q) {
                    $q->where('date', now()->toDateString())->where('status', 'out');
                }
            ])->get();

        // هيكلة البيانات لتغذية لوحة تتبع الأرواح في الطوارئ (حريق، تسريب غاز)
        $musterData = $employees->map(function ($employee) {
            return [
                'id'              => $employee->id,
                'full_name'       => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'phone'           => $employee->phone,
                'department'      => $employee->department?->name,
                'position'        => $employee->position?->name,
                // استدعاء محرك الحالات اللحظي المدمج بالموديل (Inside | Temporary_Out | Outside_Duty)
                'presence_status' => $employee->current_presence_status,
            ];
        });

        return response()->json([
            'generated_at' => now()->toDateTimeString(),
            'summary'      => [
                'total_inside'   => $musterData->where('presence_status', 'Inside')->count(),
                'total_temp_out' => $musterData->where('presence_status', 'Temporary_Out')->count(),
                'total_outside'  => $musterData->where('presence_status', 'Outside_Duty')->count(),
            ],
            'records'      => $musterData->values()->all()
        ]);
    }
}
