<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Accounting\Models\Account;
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
                'price_list_id',
                'payable_account_id',
                'receivable_account_id',
                'credit_limit',
                'credit_period_days',
                'phone',
            ])
            ->with([
                'currency:id,code,name,symbol',
                'priceList:id,name,code,is_default',
                'payableAccount:id,code,name',
                'receivableAccount:id,code,name',
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * البحث السريع عن شريك/عميل برقم الهاتف مع دعم صيغ الأرقام المحلية والدولية
     */
    public function findByPhone(string $phone, string $role = 'customer'): ?Partner
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $variants = [$phone, $digits];
        if (str_starts_with($digits, '00249')) {
            $core = substr($digits, 5);
            $variants[] = '0' . $core;
            $variants[] = '249' . $core;
            $variants[] = '+249' . $core;
            $variants[] = $core;
        } elseif (str_starts_with($digits, '249') && strlen($digits) === 12) {
            $core = substr($digits, 3);
            $variants[] = '0' . $core;
            $variants[] = '+249' . $core;
            $variants[] = $core;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $core = substr($digits, 1);
            $variants[] = '249' . $core;
            $variants[] = '+249' . $core;
            $variants[] = $core;
        } elseif (strlen($digits) === 9) {
            $variants[] = '0' . $digits;
            $variants[] = '249' . $digits;
            $variants[] = '+249' . $digits;
        }

        $variants = array_values(array_unique(array_filter($variants)));

        return Partner::query()
            ->when($role === 'customer', fn (Builder $q) => $q->customers())
            ->when($role === 'supplier', fn (Builder $q) => $q->suppliers())
            ->active()
            ->where(function (Builder $q) use ($variants, $digits): void {
                $q->whereIn('phone', $variants);
                if (strlen($digits) >= 7) {
                    $tail = substr($digits, -9);
                    $q->orWhere('phone', 'like', "%{$tail}");
                }
            })
            ->with(['currency', 'priceList', 'receivableAccount', 'payableAccount'])
            ->first();
    }

    /**
     * جلب قائمة الشركاء مع الفلترة والبحث
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Partner::query()
            ->with(['currency', 'priceList', 'receivableAccount', 'payableAccount'])
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
            $code = $this->sequenceService->generateNumber('partners', null, 'BP');

            $data['partner_code'] = $code;
            $data['created_by'] = Auth::id();
            $data['status'] = $data['status'] ?? PartnerStatus::ACTIVE->value;
            $data['tax_treatment'] = $data['tax_treatment'] ?? PartnerTaxTreatment::TAXABLE->value;

            $data = $this->assignDefaultAccounts($data);

            $partner = Partner::create($data);

            return $partner->load(['currency', 'priceList', 'receivableAccount', 'payableAccount']);
        });
    }

    /**
     * تعديل بيانات شريك مع ضمان وجود الحسابات التجميعية للأدوار المفعلة
     */
    public function update(Partner $partner, array $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            $data['updated_by'] = Auth::id();

            unset($data['partner_code']);

            $data = $this->assignDefaultAccounts($data, $partner);

            $partner->update($data);

            return $partner->fresh(['currency', 'priceList', 'receivableAccount', 'payableAccount']);
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

        $currentPayable = $data['payable_account_id'] ?? $partner?->payable_account_id;
        if ($isSupplier && empty($currentPayable)) {
            $defaultPayableId = AccountMapping::where('key', 'purchases_payable')->value('account_id');
            if ($defaultPayableId) {
                $data['payable_account_id'] = (int) $defaultPayableId;
            }
        }

        $currentReceivable = $data['receivable_account_id'] ?? $partner?->receivable_account_id;
        if ($isCustomer && empty($currentReceivable)) {
            $defaultReceivableId = AccountMapping::whereIn('key', ['sales_receivable', 'sales_receivables'])
                ->value('account_id')
                ?? Account::where('code', '11201')->value('id');

            if ($defaultReceivableId) {
                $data['receivable_account_id'] = (int) $defaultReceivableId;
            }
        }

        return $data;
    }
}