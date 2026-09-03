<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\SearchProviders;

use App\Modules\Accounting\Contracts\PartySearchProviderInterface;
use App\Modules\Accounting\Models\AccountMapping;
use App\Modules\Core\Models\Partner;
use App\Modules\Core\Enums\PartnerStatus;

class PartnerSearchProvider implements PartySearchProviderInterface
{
    public function getKey(): string
    {
        return 'partner';
    }

    public function getLabel(): string
    {
        return 'جهات التعامل (عملاء وموردين)';
    }

    public function search(string $query, int $limit = 10): array
    {
        // 1. جلب حسابات المراقبة المرتبطة بالعملاء والموردين من جدول الربط المحاسبي
        $mappings = AccountMapping::with('account')
            ->whereIn('key', ['sales_receivables', 'purchases_payable'])
            ->get()
            ->keyBy('key');

        // حساب مراقبة العملاء (الذمم المدينة)
        $customerMapping = $mappings->get('sales_receivables');
        $customerAccountId = $customerMapping?->account_id;
        $customerAccountDisplay = $customerMapping?->account
            ? "{$customerMapping->account->code} - {$customerMapping->account->name}"
            : 'العملاء / ذمم مدينة';

        // حساب مراقبة الموردين (الذمم الدائنة)
        $supplierMapping = $mappings->get('purchases_payable');
        $supplierAccountId = $supplierMapping?->account_id;
        $supplierAccountDisplay = $supplierMapping?->account
            ? "{$supplierMapping->account->code} - {$supplierMapping->account->name}"
            : 'الموردين / ذمم دائنة';

        // 2. البحث في جدول الشركاء الفعّالين فقط
        $partners = Partner::query()
            ->where('status', PartnerStatus::ACTIVE)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('commercial_name', 'like', "%{$query}%")
                  ->orWhere('partner_code', 'like', "%{$query}%")
                  ->orWhere('tax_number', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        $results = [];

        foreach ($partners as $partner) {
            // إضافة خيار العميل إذا كان الشريك مسجلاً كعميل
            if ($partner->is_customer) {
                $results[] = [
                    'key'                  => "partner_cust_{$partner->id}",
                    'code'                 => (string) $partner->partner_code,
                    'name'                 => $partner->name . ($partner->is_supplier ? ' (عميل)' : ''),
                    'type_key'             => 'customer',
                    'type_label'           => 'عميل',
                    'badge_color'          => 'emerald',
                    'account_id'           => $customerAccountId,
                    'party_type'           => Partner::class,
                    'party_id'             => (string) $partner->id,
                    'requires_cost_center' => false,
                    'parent_account_name'  => $customerAccountDisplay,
                ];
            }

            // إضافة خيار المورد إذا كان الشريك مسجلاً كمورد
            if ($partner->is_supplier) {
                $results[] = [
                    'key'                  => "partner_supp_{$partner->id}",
                    'code'                 => (string) $partner->partner_code,
                    'name'                 => $partner->name . ($partner->is_customer ? ' (مورد)' : ''),
                    'type_key'             => 'supplier',
                    'type_label'           => 'مورد',
                    'badge_color'          => 'amber',
                    'account_id'           => $supplierAccountId,
                    'party_type'           => Partner::class,
                    'party_id'             => (string) $partner->id,
                    'requires_cost_center' => false,
                    'parent_account_name'  => $supplierAccountDisplay,
                ];
            }
        }

        return $results;
    }
}