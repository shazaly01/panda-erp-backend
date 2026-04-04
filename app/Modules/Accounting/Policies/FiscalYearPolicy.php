<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\FiscalYear;
use Illuminate\Auth\Access\HandlesAuthorization;

class FiscalYearPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('fiscal_year.view');
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->can('fiscal_year.view');
    }

    public function create(User $user): bool
    {
        return $user->can('fiscal_year.create');
    }

    public function update(User $user, FiscalYear $fiscalYear): bool
    {
        return $user->can('fiscal_year.update');
    }

    public function delete(User $user, FiscalYear $fiscalYear): bool
    {
        // نمنع حذف السنة إذا كانت تحتوي على قيود (حماية منطقية)
        // حالياً سنربطها بالصلاحية فقط
        return $user->can('fiscal_year.delete'); // تأكد من إضافتها للـ Seeder إذا لم تكن موجودة
    }
}
