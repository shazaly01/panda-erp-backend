<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;

// استدعاء أمر الكونسول الخاص بتسجيل الحضور التلقائي
use App\Modules\HR\Console\Commands\RecordAutoAttendanceCommand;

class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('Super Admin') ? true : null;
        });

        // 1. تسجيل ربط العلاقات المتعددة (Morph Map) للجهات
        $this->registerMorphMaps();

        // 2. تسجيل سياسات النظام الأساسي (Core)
        $this->registerCorePolicies();

        // 3. تسجيل سياسات الحسابات (Accounting)
        $this->registerAccountingPolicies();

        // 4. تسجيل سياسات الموارد البشرية (HR)
        $this->registerHrPolicies();

        // 5. تسجيل سياسات إدارة المخزون (Inventory)
        $this->registerInventoryPolicies();

        // 6. تسجيل أوامر الكونسول (Console Commands)
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
     * تسجيل مسميات الأطراف للقيود والسندات (Polymorphic Mapping)
     */
    protected function registerMorphMaps(): void
    {
        Relation::morphMap([
            'partner'  => \App\Modules\Core\Models\Partner::class,
            'employee' => \App\Modules\HR\Models\Employee::class,
        ]);
    }

    /**
     * تسجيل سياسات موديول Core
     */
    protected function registerCorePolicies(): void
    {
        Gate::policy(\App\Modules\Core\Models\Partner::class, \App\Modules\Core\Policies\PartnerPolicy::class);
        Gate::policy(\App\Modules\Core\Models\Sequence::class, \App\Modules\Core\Policies\SequencePolicy::class);
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
        Gate::policy(\App\Modules\Accounting\Models\Budget::class, \App\Modules\Accounting\Policies\BudgetPolicy::class);
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
        Gate::policy(\App\Modules\HR\Models\InternshipApplication::class, \App\Modules\HR\Policies\InternshipApplicationPolicy::class);
    }

    /**
     * تسجيل سياسات موديول إدارة المخزون بمسارات مباشرة ومدمجة
     */
    protected function registerInventoryPolicies(): void
    {
        Gate::policy(\App\Modules\Inventory\Models\Unit::class, \App\Modules\Inventory\Policies\UnitPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Category::class, \App\Modules\Inventory\Policies\CategoryPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Warehouse::class, \App\Modules\Inventory\Policies\WarehousePolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\WarehouseLocation::class, \App\Modules\Inventory\Policies\WarehouseLocationPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Product::class, \App\Modules\Inventory\Policies\ProductPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\PriceList::class, \App\Modules\Inventory\Policies\PriceListPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\StockBatch::class, \App\Modules\Inventory\Policies\StockBatchPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Transfer::class, \App\Modules\Inventory\Policies\TransferPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Adjustment::class, \App\Modules\Inventory\Policies\AdjustmentPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\Bom::class, \App\Modules\Inventory\Policies\BomPolicy::class);
        Gate::policy(\App\Modules\Inventory\Models\ProductionOrder::class, \App\Modules\Inventory\Policies\ProductionOrderPolicy::class);
        Gate::policy(\App\Modules\Inventory\Policies\InventoryReportPolicy::class, \App\Modules\Inventory\Policies\InventoryReportPolicy::class);
    }

    /**
     * تحميل المكونات الديناميكية للموديول
     */
    protected function loadModule(string $moduleName): void
    {
        $modulePath = app_path("Modules/{$moduleName}");

        // Load Routes (API)
        $routesPath = File::exists($modulePath . '/Routes/api.php')
            ? $modulePath . '/Routes/api.php'
            : (File::exists($modulePath . '/routes/api.php') ? $modulePath . '/routes/api.php' : null);

        if ($routesPath) {
            Route::prefix('api')
                ->middleware('api')
                ->group($routesPath);
        }

        // Load Migrations
        if (File::exists($modulePath . '/Database/Migrations')) {
            $this->loadMigrationsFrom($modulePath . '/Database/Migrations');
        }
    }
}