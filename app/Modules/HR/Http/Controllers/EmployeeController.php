<?php

namespace App\Modules\HR\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Http\Resources\EmployeeResource;
use App\Modules\HR\Http\Requests\Employee\StoreEmployeeRequest;
use App\Modules\HR\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\Core\Services\SequenceService;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Employee::class, 'employee');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['department', 'position', 'latestShift.shift']);

        // 1. فلتر الإدارة
        if ($request->filled('department_id') && is_numeric($request->department_id)) {
            $query->where('department_id', $request->department_id);
        }

        // 2. فلتر المسمى الوظيفي
        if ($request->filled('position_id') && is_numeric($request->position_id)) {
            $query->where('position_id', $request->position_id);
        }

        // 3. فلتر الحالة
        if ($request->filled('status') && $request->status !== 'null' && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // 4. فلتر البحث المجمع
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $employees = $query->latest('id')->paginate(20);

        return EmployeeResource::collection($employees)->response();
    }

    public function store(StoreEmployeeRequest $request, SequenceService $sequenceService): JsonResponse
    {
        $validatedData = $request->validated();

        // 🌟 المنطق الذكي للترقيم المتوافق مع الـ Enterprise ERP
        if (empty($validatedData['employee_number'])) {
            // استخدام الكود العالمي (hr_employee) كما تم تعريفه في CoreConfigurationSeeder
            $validatedData['employee_number'] = $sequenceService->generateNumber('hr_employee');
        }

        $employee = Employee::create($validatedData);

        return response()->json([
            'message' => 'تم إضافة الموظف بنجاح',
            'data' => new EmployeeResource($employee->load(['department', 'position'])),
        ], 201);
    }

    public function show(Employee $employee): JsonResponse
    {
        // تحميل العلاقات المهمة
        $employee->load([
            'department',
            'position',
            'manager',
            'currentContract.salaryStructure',
            // 'employeeShifts.shift' // تأكد من وجود هذه العلاقة في الموديل
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee)
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث بيانات الموظف بنجاح',
            'data' => new EmployeeResource($employee->load(['department', 'position'])),
        ]);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        // حماية النظام: لا يمكن حذف موظف لديه عقد عمل نشط
        if ($employee->currentContract()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الموظف لوجود عقد عمل نشط. يرجى إنهاء العقد أولاً.'
            ], 422);
        }

        $employee->delete(); // Soft Delete

        return response()->json([
            'message' => 'تم أرشفة الموظف بنجاح'
        ]);
    }

    /**
     * كشف الحساب المالي للموظف (Sub-Ledger Statement)
     * يجلب كل الاستحقاقات (دائن) والمدفوعات (مدين) من القيود المحاسبية
     */
    public function getFinancialStatement($id): JsonResponse
    {
        // التحقق من الصلاحيات (تم التعديل لتجنب الخطأ الإملائي في المسار)
        $this->authorize('view', \App\Modules\HR\Models\Employee::class);

        $employee = \App\Modules\HR\Models\Employee::findOrFail($id);

        // بناء استعلام يربط تفاصيل القيد برأس القيد لجلب التاريخ والحالة
        $transactions = \Illuminate\Support\Facades\DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted') // القيود المعتمدة فقط
            ->where('journal_entry_details.party_type', 'employee')
            // 🌟 التصحيح المعماري: البحث يجب أن يكون باستخدام الـ ID الخاص بالموظف وليس رقم الموظف التسلسلي
            ->where('journal_entry_details.party_id', (string)$employee->id)
            ->select(
                'journal_entries.date',
                'journal_entries.entry_number',
                'journal_entries.description as entry_description',
                'journal_entry_details.description as detail_description',
                'journal_entry_details.debit',
                'journal_entry_details.credit'
            )
            ->orderBy('journal_entries.date', 'asc') // ترتيب زمني تصاعدي (من الأقدم للأحدث)
            ->orderBy('journal_entries.id', 'asc')
            ->get();

        $runningBalance = 0;
        $statement = [];

        // حساب الرصيد التراكمي (Running Balance)
        foreach ($transactions as $transaction) {
            // حساب الرواتب المستحقة هو حساب التزام (Liability)
            // الرصيد يزيد بالدائن (له) ويقل بالمدين (عليه)
            $runningBalance += $transaction->credit;
            $runningBalance -= $transaction->debit;

            $statement[] = [
                'date'         => $transaction->date,
                'entry_number' => $transaction->entry_number,
                'description'  => $transaction->detail_description ?: $transaction->entry_description,
                'credit'       => (float) $transaction->credit, // استحقاق (راتب نزل في حسابه)
                'debit'        => (float) $transaction->debit,  // مدفوعات (تم صرفه له/سلف)
                'balance'      => (float) $runningBalance,      // المتبقي الذي تطلبه به الشركة
            ];
        }

        return response()->json([
            'message' => 'تم جلب كشف الحساب بنجاح',
            'data' => [
                'employee' => [
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'current_balance' => (float) $runningBalance
                ],
                'statement' => $statement
            ]
        ]);
    }
}
