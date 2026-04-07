<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Policies\AccountPolicy;

use App\Modules\Accounting\Models\CostCenter;
use App\Modules\Accounting\Policies\CostCenterPolicy;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Policies\JournalEntryPolicy;

use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Policies\FiscalYearPolicy;

use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Policies\CurrencyPolicy;

use App\Modules\Accounting\Models\Box;
use App\Modules\Accounting\Policies\BoxPolicy;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Policies\BankAccountPolicy;

use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Policies\VoucherPolicy;

// --- 2. استدعاءات موديول الموارد البشرية (HR) - الجديدة ---
use App\Modules\HR\Models\Department;
use App\Modules\HR\Policies\DepartmentPolicy;
use App\Modules\HR\Models\Position;
use App\Modules\HR\Policies\PositionPolicy;
use App\Modules\HR\Models\SalaryRule;
use App\Modules\HR\Policies\SalaryRulePolicy;
use App\Modules\HR\Models\SalaryStructure;
use App\Modules\HR\Policies\SalaryStructurePolicy;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Models\Contract;
use App\Modules\HR\Policies\ContractPolicy;

use App\Modules\HR\Models\PayrollBatch;     // <--- جديد
use App\Modules\HR\Policies\PayrollPolicy;

use App\Modules\HR\Models\AttendanceLog;
use App\Modules\HR\Policies\AttendanceLogPolicy;

use App\Modules\HR\Models\Shift;
use App\Modules\HR\Policies\ShiftPolicy;

use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Policies\LeaveRequestPolicy; // أو LeaveRequestPolicy حسب التسمية لديك

use App\Modules\HR\Models\Loan;
use App\Modules\HR\Policies\LoanPolicy;

class ModuleServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(CostCenter::class, CostCenterPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(FiscalYear::class, FiscalYearPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(Box::class, BoxPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Voucher::class, VoucherPolicy::class);


        // 2. تسجيل سياسات الموارد البشرية (HR)
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(SalaryRule::class, SalaryRulePolicy::class);
        Gate::policy(SalaryStructure::class, SalaryStructurePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(PayrollBatch::class, PayrollPolicy::class);
        Gate::policy(AttendanceLog::class, AttendanceLogPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);

        $modulesPath = app_path('Modules');

        if (!File::exists($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $module) {
            $this->loadModule(basename($module));
        }
    }

    protected function loadModule(string $moduleName): void
    {
        $modulePath = app_path("Modules/{$moduleName}");

        // 1. Load Routes (API)
        // يقوم بتحميل ملف api.php ويضيف له البادئة api/v1
        if (File::exists($modulePath . '/Routes/api.php')) {
            Route::prefix('api')
                ->middleware('api')
                ->group($modulePath . '/Routes/api.php');
        }

        // 2. Load Migrations
        // يخبر لارافيل بمكان ملفات الترحيل الخاصة بالموديول
        if (File::exists($modulePath . '/Database/Migrations')) {
            $this->loadMigrationsFrom($modulePath . '/Database/Migrations');
        }

        // 3. Register Policies (Auto-Discovery approach can be added here)
        // حالياً سنعتمد التسجيل اليدوي داخل هذا الملف كما اتفقنا في الدليل
        // Gate::policy(Model::class, Policy::class);
    }
}
