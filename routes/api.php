<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- استيراد الـ Controllers القديمة والجديدة ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DocumentController; // موديول الأرشفة العالمي
use App\Http\Controllers\Api\BrandingController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// --- المسارات العامة (Public Routes) ---
Route::get('/branding', [BrandingController::class, 'index']);
// تم تطبيق الحماية الأمنية (Rate Limiting) هنا لمنع استهلاك رصيد SMS (بحد أقصى 3 طلبات في الدقيقة)
Route::post('/login', [AuthController::class, 'login']);

Route::post('/send-otp', [AuthController::class, 'sendOtp'])
    ->middleware('throttle:3,1'); // 🛡️ حماية ضد استهلاك رسائل التفعيل عند التسجيل

Route::post('/register', [AuthController::class, 'register']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1'); // 🛡️ حماية ضد استهلاك الرسائل عند طلب استعادة كلمة المرور

Route::post('/reset-password', [AuthController::class, 'resetPassword']);


// --- المسارات المحمية (Protected Routes) ---
// تتطلب مصادقة باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // مسارات الحسابات من الموديول المنفصل
    require base_path('app/Modules/Accounting/Routes/api.php');

    // --- مسارات إدارة النسخ الاحتياطي ---
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])
            ->middleware('can:backup.view');

        Route::post('/', [BackupController::class, 'store'])
            ->middleware('can:backup.create');

        Route::get('/download', [BackupController::class, 'download'])
            ->middleware('can:backup.download');

        Route::delete('/', [BackupController::class, 'destroy'])
            ->middleware('can:backup.delete');
    });

    // --- مسارات نظام إدارة المستندات والأرشفة (DMS Routes) ---
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::apiResource('documents', DocumentController::class);

    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);

    // جلب بيانات المستخدم الحالي مع أدواره وصلاحياته
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('roles:id,name', 'roles.permissions:id,name');
        return response()->json($user);
    });

    // --- مسارات إدارة الأدوار والصلاحيات ---
    Route::get('roles/permissions', [RoleController::class, 'getAllPermissions'])->name('roles.permissions');
    Route::apiResource('roles', RoleController::class);

    // --- مسارات إدارة المستخدمين ---
    Route::put('users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');

    // [إضافة مستحدثة] مسار تعليق وتجميد الحسابات أو إعادة تنشيطها ليتطابق مع طلب واجهة الـ Axios
    Route::put('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    Route::apiResource('users', UserController::class);

});
