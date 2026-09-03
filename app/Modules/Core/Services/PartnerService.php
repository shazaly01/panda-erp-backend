<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Partner;
use App\Modules\Core\Enums\PartnerStatus;
use App\Modules\Core\Enums\PartnerTaxTreatment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerService
{
    public function __construct(
        protected SequenceService $sequenceService
    ) {}

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
     * إنشاء شريك جديد مع توليد الكود التسلسلي
     */
    public function create(array $data): Partner
    {
        return DB::transaction(function () use ($data): Partner {
            // توليد الكود التسلسلي آلياً للشريك
            $code = $this->sequenceService->generateNext('BP', 'partners');

            $data['partner_code'] = $code;
            $data['created_by'] = Auth::id();
            $data['status'] = $data['status'] ?? PartnerStatus::ACTIVE->value;
            $data['tax_treatment'] = $data['tax_treatment'] ?? PartnerTaxTreatment::TAXABLE->value;

            $partner = Partner::create($data);

            return $partner->load(['currency', 'receivableAccount', 'payableAccount']);
        });
    }

    /**
     * تعديل بيانات شريك
     */
    public function update(Partner $partner, array $data): Partner
    {
        return DB::transaction(function () use ($partner, $data): Partner {
            $data['updated_by'] = Auth::id();

            // حماية الكود التسلسلي من التعديل اليدوي
            unset($data['partner_code']);

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
}