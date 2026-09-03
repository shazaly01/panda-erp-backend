<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\SearchProviders;

use App\Modules\Accounting\Contracts\PartySearchProviderInterface;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\HR\Models\Employee;

class EmployeeSearchProvider implements PartySearchProviderInterface
{
    public function getKey(): string
    {
        return 'employee';
    }

    public function getLabel(): string
    {
        return 'موظف / سلف';
    }

    public function search(string $query, int $limit = 10): array
    {
        // جلب حساب مراقبة سلف وعهد الموظفين من جدول الربط المحاسبي
        $mapping = AccountMapping::with('account')
            ->where('key', 'hr_employee_loans')
            ->first();

        $accountId = $mapping?->account_id;
        $accountDisplay = $mapping?->account
            ? "{$mapping->account->code} - {$mapping->account->name}"
            : 'سلف وعهد الموظفين';

        $employees = Employee::query()
            ->where(function ($q) use ($query) {
                $q->where('full_name', 'like', "%{$query}%")
                  ->orWhere('employee_number', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('barcode', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $employees->map(function (Employee $employee) use ($accountId, $accountDisplay) {
            $code = $employee->employee_number ?: ($employee->barcode ?: (string) $employee->id);

            return [
                'key'                  => "emp_{$employee->id}",
                'code'                 => (string) $code,
                'name'                 => $employee->full_name,
                'type_key'             => $this->getKey(),
                'type_label'           => $this->getLabel(),
                'badge_color'          => 'purple',
                'account_id'           => $accountId,
                'party_type'           => Employee::class,
                'party_id'             => (string) $employee->id,
                'requires_cost_center' => false,
                'parent_account_name'  => $accountDisplay,
            ];
        })->toArray();
    }
}