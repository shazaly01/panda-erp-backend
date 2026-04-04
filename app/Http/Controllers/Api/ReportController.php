<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * تقرير عام: ملخص مالي لكل الشركات
     * يعرض: عدد المشاريع، إجمالي القيم المستحقة، المدفوع، والمتبقي لكل شركة
     */
    public function companiesSummary(): JsonResponse
    {
        // --- [التعديل هنا] ---
        // تم تغيير 'contract_value' إلى 'due_value' في الاستعلام
       $companies = Company::withCount('projects')
    ->withSum('projects as total_due_value', 'due_value')
    ->withSum('projects as total_contract_value', 'contract_value') // <--- إضافة هذا السطر
    ->get();


        $data = $companies->map(function ($company) {
            // حساب إجمالي المدفوعات لكل مشاريع هذه الشركة
            $totalPaid = $company->projects()->withSum('payments', 'amount')->get()->sum('payments_sum_amount');

            // --- [التعديل هنا] ---
            // تم تغيير 'total_contracts_value' إلى 'total_due_value'
            $dueValue = (float) ($company->total_due_value ?? 0); // <--- تم التغيير
            $paidValue = (float) ($totalPaid ?? 0);

            return [
                'id' => $company->id,
                'name' => $company->name,
                'license_number' => $company->license_number,
                'tax_number' => $company->tax_number,
                'projects_count' => $company->projects_count,
                'total_contract_value' => (float) ($company->total_contract_value ?? 0),
                // --- [التعديل هنا] ---
                // تم تغيير اسم المفتاح في الـ JSON الناتج
                'total_due_value' => $dueValue, // <--- تم التغيير
                'total_paid' => $paidValue,
                'total_remaining' => $dueValue - $paidValue,
            ];
        });

        return response()->json([
            'data' => $data,
            'grand_summary' => [
                'total_companies' => $data->count(),
                'total_projects' => $data->sum('projects_count'),
                'grand_total_contract_value' => $data->sum('total_contract_value'),
                // --- [التعديل هنا] ---
                // تم تغيير أسماء المفاتيح في الإجماليات
                'grand_total_due_value' => $data->sum('total_due_value'), // <--- تم التغيير
                'grand_total_paid' => $data->sum('total_paid'),
                'grand_total_remaining' => $data->sum('total_remaining'),
            ]
        ]);
    }

    /**
     * كشف حساب لشركة معينة
     */
    public function companyStatement(Company $company): JsonResponse
    {
        // تحميل المشاريع مع حساب إجمالي الدفعات لكل مشروع
        $projects = $company->projects()->withSum('payments', 'amount')->get();

        $projectsData = $projects->map(function ($project) {
            $totalPaid = $project->payments_sum_amount ?? 0;
            return [
                'id' => $project->id,
                'name' => $project->name,
                'contract_value' => (float) $project->contract_value,
                // --- [التعديل هنا] ---
                // تم تغيير 'contract_value' إلى 'due_value'
                'due_value' => (float) $project->due_value, // <--- تم التغيير
                'total_paid' => (float) $totalPaid,
                'remaining' => (float) $project->due_value - $totalPaid, // <--- تم التغيير
            ];
        });

        // --- [التعديل هنا] ---
        // حساب الإجماليات باستخدام الاسم الجديد
        $totalDueValue = $projectsData->sum('due_value'); // <--- تم التغيير
        $totalPaymentsReceived = $projectsData->sum('total_paid');

        return response()->json([
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'tax_number' => $company->tax_number,
                    'license_number' => $company->license_number,
                    'owner_name' => $company->owner_name,
                    'address' => $company->address,
                ],
                'projects' => $projectsData,
                'summary' => [
                    // --- [التعديل هنا] ---
                    // تم تغيير أسماء المفاتيح في ملخص كشف الحساب
                    'total_due_value' => $totalDueValue, // <--- تم التغيير
                    'total_contract_value' => $projectsData->sum('contract_value'),
                    'total_payments_received' => $totalPaymentsReceived,
                    'total_remaining' => $totalDueValue - $totalPaymentsReceived,
                ]
            ]
        ]);
    }
}
