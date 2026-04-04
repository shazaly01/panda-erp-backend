<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- استيراد الـ Controllers الجديدة ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\BackupController;

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
// لا تحتاج إلى مصادقة
Route::post('/login', [AuthController::class, 'login']);


// --- المسارات المحمية (Protected Routes) ---
// تتطلب مصادقة باستخدام Sanctum
Route::middleware('auth:sanctum')->group(function () {

require base_path('app/Modules/Accounting/Routes/api.php');

// --- مسارات إدارة النسخ الاحتياطي ---
    Route::prefix('backups')->name('backups.')->group(function () {
        // عرض القائمة (يتطلب صلاحية backup.view)
        Route::get('/', [BackupController::class, 'index'])
            ->middleware('can:backup.view');

        // إنشاء نسخة جديدة (يتطلب صلاحية backup.create)
        Route::post('/', [BackupController::class, 'store'])
            ->middleware('can:backup.create');

        // تحميل النسخة (يتطلب صلاحية backup.download)
        Route::get('/download', [BackupController::class, 'download'])
            ->middleware('can:backup.download');

        // حذف النسخة (يتطلب صلاحية backup.delete)
        Route::delete('/', [BackupController::class, 'destroy'])
            ->middleware('can:backup.delete');
    });


    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);

    // جلب بيانات المستخدم الحالي مع أدواره وصلاحياته
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('roles:id,name', 'roles.permissions:id,name');
        return response()->json($user);
    });

    // --- مسارات إدارة الأدوار والصلاحيات ---
    // جلب كل الصلاحيات المتاحة في النظام (مفيد عند تعديل دور)
    Route::get('roles/permissions', [RoleController::class, 'getAllPermissions'])->name('roles.permissions');
    Route::apiResource('roles', RoleController::class);

    // --- مسارات إدارة المستخدمين ---
    Route::apiResource('users', UserController::class);



});
