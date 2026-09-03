<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Contracts\PartySearchProviderInterface;
use App\Modules\Accounting\Services\SearchProviders\AccountSearchProvider;
use App\Modules\Accounting\Services\SearchProviders\WarehouseSearchProvider;
use App\Modules\Accounting\Services\SearchProviders\EmployeeSearchProvider;
use App\Modules\Accounting\Services\SearchProviders\PartnerSearchProvider;

class AccountPartySearchService
{
    /**
     * @var array<string, PartySearchProviderInterface>
     */
    protected array $providers = [];

    public function __construct(
        AccountSearchProvider $accountProvider,
        WarehouseSearchProvider $warehouseProvider,
        EmployeeSearchProvider $employeeProvider,
        PartnerSearchProvider $partnerProvider
    ) {
        $this->registerProvider($accountProvider);
        $this->registerProvider($warehouseProvider);
        $this->registerProvider($employeeProvider);
        $this->registerProvider($partnerProvider);
    }

    /**
     * تسجيل مزوّد بحث جديد ديناميكياً
     */
    public function registerProvider(PartySearchProviderInterface $provider): void
    {
        $this->providers[$provider->getKey()] = $provider;
    }

    /**
     * استعلام البحث الموحد عبر كافة المزودات المحددة
     *
     * @param string $query نص البحث
     * @param array<int, string> $allowedTypes الأنواع المسموح بالبحث فيها (فارغ = الكل)
     * @param int $limitPerType الحد الأقصى للنتائج لكل مزود
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $allowedTypes = [], int $limitPerType = 10): array
    {
        $trimmedQuery = trim($query);

        if (empty($trimmedQuery)) {
            return [];
        }

        $results = [];

        foreach ($this->providers as $key => $provider) {
            if (!empty($allowedTypes) && !in_array($key, $allowedTypes, true)) {
                continue;
            }

            $providerResults = $provider->search($trimmedQuery, $limitPerType);
            $results = array_merge($results, $providerResults);
        }

        return $results;
    }

    /**
     * استرجاع قائمة بجميع المفاتيح والأسماء المسجلة للمزودات
     *
     * @return array<int, array<string, string>>
     */
    public function getAvailableTypes(): array
    {
        $types = [];

        foreach ($this->providers as $provider) {
            $types[] = [
                'key'   => $provider->getKey(),
                'label' => $provider->getLabel(),
            ];
        }

        return $types;
    }
}