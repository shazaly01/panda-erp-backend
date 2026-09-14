<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Core\Enums\PartnerStatus;
use App\Modules\Core\Enums\PartnerTaxTreatment;
use App\Modules\Core\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

    /**
     * جلب قائمة سريعة ومختصرة للشركاء للاستخدام في القوائم المنسدلة (Selector)
     * محملة بالبيانات التشغيلية والمالية الأساسية (Rich Payload)
     */
    public function getSelector(string $role = 'supplier'): Collection
    {
        return Partner::query()
            ->when($role === 'supplier', function (Builder $query): void {
                $query->suppliers();
            })
            ->when($role === 'customer', function (Builder $query): void {
                $query->customers();
            })
            ->active()
            ->select([
                'id',
                'partner_code',
                'name',
                'commercial_name',
                'tax_number',
                'tax_treatment',
                'currency_id',
                'payable_account_id',
                'receivable_account_id',
                'credit_limit',
                'credit_period_days',
                'phone',
            ])
            ->with([
                'currency:id,code,name,symbol',
                'payableAccount:id,code,name',
                'receivableAccount:id,code,name',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * جلب قائمة الشركاء مع الفلترة والبحث
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Partner::query()
            ->with(['currency', 'receivableAccount', 'payableAccount'])
            ->when(! empty($filters['search']), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('commercial_name', 'like', "%{$search}%")
                      ->orWhere('partner_code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('tax_number', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['role']), function (Builder $query) use ($filters): void {
                match ($filters['role']) {
                    'customer' => $query->where('is_customer', true),
                    'supplier' => $query->where('is_supplier', true),
                    default => null,
                };
            })
            ->when(! empty($filters['type']), function (Builder $query) use ($filters): void {
                $query->where('type', $filters['type']);
            })
            ->when(! empty($filters['status']), function (Builder $query) use ($filters): void {
                $query->where('status', $filters['status']);
            })
            ->when(! empty($filters['tax_treatment']), function (Builder $query) use ($filters): void {
                $query->where('tax_treatment', $filters['tax_treatment']);
            })
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * إنشاء شريك جديد مع توليد الكود والربط التلقائي بالحسابات التجميعية
     */
    public function create(array $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            // توليد الكود التسلسلي آلياً للشريك
            $code = $this->sequenceService->generateNumber('partners', null, 'BP');

            $data['partner_code'] = $code;
            $data['created_by'] = Auth::id();
            $data['status'] = $data['status'] ?? PartnerStatus::ACTIVE->value;
            $data['tax_treatment'] = $data['tax_treatment'] ?? PartnerTaxTreatment::TAXABLE->value;

            // إسناد الحسابات التجميعية الافتراضية إذا لم يتم تحديدها يدوياً
            $data = $this->assignDefaultAccounts($data);

            $partner = Partner::create($data);

            return $partner->load(['currency', 'receivableAccount', 'payableAccount']);
        });
    }

    /**
     * تعديل بيانات شريك مع ضمان وجود الحسابات التجميعية للأدوار المفعلة
     */
    public function update(Partner $partner, array $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            $data['updated_by'] = Auth::id();

            // حماية الكود التسلسلي من التعديل اليدوي
            unset($data['partner_code']);

            // فحص وتعيين الحساب التجميعي في حال تفعيل دور المورد أو العميل دون تحديد حساب
            $data = $this->assignDefaultAccounts($data, $partner);

            $partner->update($data);

            return $partner->fresh(['currency', 'receivableAccount', 'payableAccount']);
        });
    }

    /**
     * حذف شريك (يخضع لحماية boot deleting بالموديل)
     */
    public function delete(Partner $partner): bool
    {
        return DB::transaction(function () use ($partner): bool {
            return (bool) $partner->delete();
        });
    }

    /**
     * ربط الطرف بالحساب التجميعي العام المناسب من خريطة الحسابات
     */
    protected function assignDefaultAccounts(array $data, ?Partner $partner = null): array
    {
        $isSupplier = isset($data['is_supplier'])
            ? (bool) $data['is_supplier']
            : ($partner ? (bool) $partner->is_supplier : false);

        $isCustomer = isset($data['is_customer'])
            ? (bool) $data['is_customer']
            : ($partner ? (bool) $partner->is_customer : false);

        // 1. ربط حساب الذمم الدائنة (الموردين) التجميعي
        $currentPayable = $data['payable_account_id'] ?? $partner?->payable_account_id;
        if ($isSupplier && empty($currentPayable)) {
            $defaultPayableId = AccountMapping::where('key', 'purchases_payable')->value('account_id');
            if ($defaultPayableId) {
                $data['payable_account_id'] = (int) $defaultPayableId;
            }
        }

        // 2. ربط حساب الذمم المدينة (العملاء) التجميعي
        $currentReceivable = $data['receivable_account_id'] ?? $partner?->receivable_account_id;
        if ($isCustomer && empty($currentReceivable)) {
            $defaultReceivableId = AccountMapping::where('key', 'sales_receivables')->value('account_id');
            if ($defaultReceivableId) {
                $data['receivable_account_id'] = (int) $defaultReceivableId;
            }
        }

        return $data;
    }
}