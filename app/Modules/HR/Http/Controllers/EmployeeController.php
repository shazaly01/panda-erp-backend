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
        $query = Employee::with(['department', 'position', 'profilePhoto']);

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

        // 5. 🌟 فلتر الموظفين الذين ليس لديهم عقود نهائياً (بدون أي سجّل في جدول العقود)
        if ($request->boolean('without_contract')) {
            $query->whereDoesntHave('contracts');
        }

        // 6. 🌟 فلتر استبعاد الموظفين الذين لديهم عقد عمل نشط (تُستخدم في شاشة إنشاء العقود)
        if ($request->boolean('without_active_contract')) {
            $query->whereDoesntHave('contracts', function ($q) {
                $q->where('is_active', true);
            });
        }

        $employees = $query->latest('id')->paginate(500);

        return EmployeeResource::collection($employees)->response();
    }

    public function store(StoreEmployeeRequest $request, SequenceService $sequenceService): JsonResponse
    {
        $validatedData = $request->validated();

        if (empty($validatedData['employee_number'])) {
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
        $employee->load([
            'department',
            'position',
            'manager',
            'currentContract.salaryStructure',
            'profilePhoto',
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
        if ($employee->currentContract()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الموظف لوجود عقد عمل نشط. يرجى إنهاء العقد أولاً.'
            ], 422);
        }

        $employee->delete();

        return response()->json([
            'message' => 'تم أرشفة الموظف بنجاح'
        ]);
    }

    /**
     * كشف الحساب المالي للموظف (Sub-Ledger Statement)
     */
    public function getFinancialStatement($id): JsonResponse
    {
        $this->authorize('view', \App\Modules\HR\Models\Employee::class);

        $employee = \App\Modules\HR\Models\Employee::findOrFail($id);

        $transactions = \Illuminate\Support\Facades\DB::table('journal_entry_details')
            ->join('journal_entries', 'journal_entry_details.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_details.party_type', 'employee')
            ->where('journal_entry_details.party_id', (string)$employee->id)
            ->select(
                'journal_entries.date',
                'journal_entries.entry_number',
                'journal_entries.description as entry_description',
                'journal_entry_details.description as detail_description',
                'journal_entry_details.debit',
                'journal_entry_details.credit'
            )
            ->orderBy('journal_entries.date', 'asc')
            ->orderBy('journal_entries.id', 'asc')
            ->get();

        $runningBalance = 0;
        $statement = [];

        foreach ($transactions as $transaction) {
            $runningBalance += $transaction->credit;
            $runningBalance -= $transaction->debit;

            $statement[] = [
                'date'         => $transaction->date,
                'entry_number' => $transaction->entry_number,
                'description'  => $transaction->detail_description ?: $transaction->entry_description,
                'credit'       => (float) $transaction->credit,
                'debit'        => (float) $transaction->debit,
                'balance'      => (float) $runningBalance,
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
