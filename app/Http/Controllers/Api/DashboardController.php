<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $companiesCount = Company::count();
        $projectsCount = Project::count();
        // --- [التعديل هنا] ---
        // تم تغيير اسم العمود من 'contract_value' إلى 'due_value'
        $totalDueValue = Project::sum('due_value');
        $totalPaymentsValue = Payment::sum('amount');

        return response()->json([
            'data' => [
                'companies_count' => $companiesCount,
                'projects_count' => $projectsCount,
                // --- [التعديل هنا] ---
                // تم تغيير اسم المفتاح ليتوافق مع الواجهة الأمامية
                'total_due_value' => (float) $totalDueValue,
                'total_payments_value' => (float) $totalPaymentsValue,
            ]
        ]);
    }
}
