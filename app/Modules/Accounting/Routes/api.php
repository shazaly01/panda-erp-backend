<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Accounting\Http\Controllers\AccountController;
use App\Modules\Accounting\Http\Controllers\CostCenterController;
use App\Modules\Accounting\Http\Controllers\JournalEntryController;
use App\Modules\Accounting\Http\Controllers\FiscalYearController;
use App\Modules\Accounting\Http\Controllers\ReportController;
use App\Modules\Accounting\Http\Controllers\AccountMappingController;

// --- استدعاءات المتحكمات الجديدة (Treasury) ---
use App\Modules\Accounting\Http\Controllers\CurrencyController;
use App\Modules\Accounting\Http\Controllers\BoxController;
use App\Modules\Accounting\Http\Controllers\BankAccountController;
use App\Modules\Accounting\Http\Controllers\VoucherController;

Route::middleware('auth:sanctum')
    ->prefix('accounting')
    ->group(function () {

    // ===========================================
    // 1. البيانات الأساسية (Master Data)
    // ===========================================
    Route::get('accounts/types', [AccountController::class, 'getTypes']);
    Route::get('accounts/suggest-code', [AccountController::class, 'suggestCode']);
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('cost-centers', CostCenterController::class);
    Route::apiResource('fiscal-years', FiscalYearController::class);

    // ===========================================
    // 2. إدارة النقدية (Treasury) - [الجديد]
    // ===========================================

    // العملات
    Route::apiResource('currencies', CurrencyController::class);

    // الخزائن (Boxes)
    Route::apiResource('boxes', BoxController::class);

    // الحسابات البنكية
    // ملاحظة: لارافيل سيفهم تلقائياً أن المتغير هو {bank_account}
    Route::apiResource('bank-accounts', BankAccountController::class);

    // ===========================================
    // 3. العمليات (Transactions)
    // ===========================================
    Route::apiResource('journal-entries', JournalEntryController::class);
    // رابط الترحيل (Posting)
    Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);

    // ===========================================
    // 4. التقارير (Reports)
    // ===========================================
    Route::prefix('reports')->group(function () {
       Route::get('account-statement', [ReportController::class, 'getAccountStatement']);
       Route::get('trial-balance', [ReportController::class, 'getTrialBalance']);
       Route::get('income-statement', [ReportController::class, 'getIncomeStatement']);
       Route::get('balance-sheet', [ReportController::class, 'getBalanceSheet']);
    });

    // ===========================================
    // 5. الإعدادات (Settings)
    // ===========================================
    Route::get('account-mappings', [AccountMappingController::class, 'index']);
    Route::put('account-mappings/{id}', [AccountMappingController::class, 'update']);
    Route::get('account-mappings/allowed-accounts/{key}', [AccountMappingController::class, 'allowedAccounts']);


    // 1. العمليات الأساسية (CRUD)
    // تنشئ الراوتات: index, show, store, update, destroy
    Route::apiResource('vouchers', VoucherController::class);

    // 2. العمليات الإضافية (Actions)
    // ترحيل السند
    Route::post('vouchers/{voucher}/post', [VoucherController::class, 'post']);

    // اعتماد السند
    Route::post('vouchers/{voucher}/approve', [VoucherController::class, 'approve']);

});
