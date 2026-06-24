<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;

// استدعاء أمر الكونسول الخاص بتسجيل الحضور التلقائي
use App\Modules\HR\Console\Commands\RecordAutoAttendanceCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // 1. تسجيل سياسات الحسابات والمبيعات (Accounting)
        $this->registerAccountingPolicies();

        // 2. تسجيل سياسات الموارد البشرية (HR)
        $this->registerHrPolicies();

        // 3. تسجيل أوامر الكونسول (Console Commands)
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecordAutoAttendanceCommand::class,
            ]);
        }

        $modulesPath = app_path('Modules');

        if (!File::exists($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $this->loadModule(basename($module));
        }
    }

    /**
     * تسجيل سياسات موديول الحسابات بمسارات مباشرة ومدمجة
     */
    protected function registerAccountingPolicies(): void
    {
        Gate::policy(\App\Modules\Accounting\Models\Account::class, \App\Modules\Accounting\Policies\AccountPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\CostCenter::class, \App\Modules\Accounting\Policies\CostCenterPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\JournalEntry::class, \App\Modules\Accounting\Policies\JournalEntryPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\FiscalYear::class, \App\Modules\Accounting\Policies\FiscalYearPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\Currency::class, \App\Modules\Accounting\Policies\CurrencyPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\Box::class, \App\Modules\Accounting\Policies\BoxPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\BankAccount::class, \App\Modules\Accounting\Policies\BankAccountPolicy::class);
        Gate::policy(\App\Modules\Accounting\Models\Voucher::class, \App\Modules\Accounting\Policies\VoucherPolicy::class);
    }

    /**
     * تسجيل سياسات موديول الموارد البشرية بمسارات مباشرة ومدمجة
     */
    protected function registerHrPolicies(): void
    {
        Gate::policy(\App\Modules\HR\Models\Department::class, \App\Modules\HR\Policies\DepartmentPolicy::class);
        Gate::policy(\App\Modules\HR\Models\Position::class, \App\Modules\HR\Policies\PositionPolicy::class);
        Gate::policy(\App\Modules\HR\Models\SalaryRule::class, \App\Modules\HR\Policies\SalaryRulePolicy::class);
        Gate::policy(\App\Modules\HR\Models\SalaryStructure::class, \App\Modules\HR\Policies\SalaryStructurePolicy::class);
        Gate::policy(\App\Modules\HR\Models\Employee::class, \App\Modules\HR\Policies\EmployeePolicy::class);
        Gate::policy(\App\Modules\HR\Models\Contract::class, \App\Modules\HR\Policies\ContractPolicy::class);
        Gate::policy(\App\Modules\HR\Models\PayrollBatch::class, \App\Modules\HR\Policies\PayrollPolicy::class);
        Gate::policy(\App\Modules\HR\Models\AttendanceLog::class, \App\Modules\HR\Policies\AttendanceLogPolicy::class);
        Gate::policy(\App\Modules\HR\Models\Shift::class, \App\Modules\HR\Policies\ShiftPolicy::class);
        Gate::policy(\App\Modules\HR\Models\LeaveRequest::class, \App\Modules\HR\Policies\LeaveRequestPolicy::class);
        Gate::policy(\App\Modules\HR\Models\Loan::class, \App\Modules\HR\Policies\LoanPolicy::class);
        Gate::policy(\App\Modules\HR\Models\HrLeavePass::class, \App\Modules\HR\Policies\HrLeavePassPolicy::class);

        // 🛡️ حقن السياسة الجديدة لطلبات التدريب لتفعيل الـ Gates في لوحة تحكم الـ HR بنجاح
        Gate::policy(\App\Modules\HR\Models\InternshipApplication::class, \App\Modules\HR\Policies\InternshipApplicationPolicy::class);
    }

    /**
     * تحميل المكونات الديناميكية للموديول
     */
    protected function loadModule(string $moduleName): void
    {
        $modulePath = app_path("Modules/{$moduleName}");

        // Load Routes (API)
        if (File::exists($modulePath . '/Routes/api.php')) {
            Route::prefix('api')
                ->middleware('api')
                ->group($modulePath . '/Routes/api.php');
        }

        // Load Migrations
        if (File::exists($modulePath . '/Database/Migrations')) {
            $this->loadMigrationsFrom($modulePath . '/Database/Migrations');
        }
    }
}
