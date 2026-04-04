<?php
// tests\ApiTestCase.php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use App\Modules\Accounting\Database\Seeders\AccountingPermissionsSeeder;
use Database\Seeders\CoreConfigurationSeeder;

abstract class ApiTestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected User $adminUser;
    protected User $accountantUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. تشغيل الـ Seeders
        $this->seed(PermissionSeeder::class);
        $this->seed(AccountingPermissionsSeeder::class);
        $this->seed(CoreConfigurationSeeder::class);

        // 2. تجهيز دور المحاسب
        $accountantRole = Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'api']);

        // --- (إضافة هامة جداً) ---
        // نضمن وجود صلاحيات السندات في قاعدة البيانات لأن السياسة (Policy) تطلبها
        // حتى لو لم تكن موجودة في الـ Seeder، سننشئها هنا للاختبار
        $voucherPermissions = [
            'payment.view', 'payment.create', 'payment.update', 'payment.delete', 'payment.post', 'payment.approve',
            'receipt.view', 'receipt.create', 'receipt.update', 'receipt.delete', 'receipt.post', 'receipt.approve',
        ];

        foreach ($voucherPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'api']);
        }
        // -------------------------

        // 3. جلب كل الصلاحيات المطلوبة (بما فيها السندات المضافة حديثاً)
        $permissions = Permission::where('name', 'like', 'account.%')
            ->orWhere('name', 'like', 'journal_entry.%')
            ->orWhere('name', 'like', 'cost_center.%')
            ->orWhere('name', 'like', 'fiscal_year.%')
            // إضافة البادئات الخاصة بالسندات
            ->orWhere('name', 'like', 'payment.%')
            ->orWhere('name', 'like', 'receipt.%')
            ->pluck('name');

        // منح الصلاحيات للدور
        $accountantRole->syncPermissions($permissions);

        // 4. إنشاء المستخدمين
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('Admin');

        $this->accountantUser = User::factory()->create();
        $this->accountantUser->assignRole($accountantRole);
    }

    protected function actingAsAccountant()
    {
        return Sanctum::actingAs($this->accountantUser);
    }

    protected function actingAsAdmin()
    {
        return Sanctum::actingAs($this->adminUser);
    }
}
