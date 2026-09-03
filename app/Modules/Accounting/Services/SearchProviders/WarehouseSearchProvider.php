<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\SearchProviders;

use App\Modules\Accounting\Contracts\PartySearchProviderInterface;
use App\Modules\Inventory\Models\Warehouse;

class WarehouseSearchProvider implements PartySearchProviderInterface
{
    public function getKey(): string
    {
        return 'warehouse';
    }

    public function getLabel(): string
    {
        return 'مستودع';
    }

    public function search(string $query, int $limit = 10): array
    {
        $warehouses = Warehouse::query()
            ->with('account')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $warehouses->map(function (Warehouse $warehouse) {
            $accountDisplay = $warehouse->account
                ? "{$warehouse->account->code} - {$warehouse->account->name}"
                : 'حساب المخزون الافتراضي';

            return [
                'key'                  => "wh_{$warehouse->id}",
                'code'                 => (string) $warehouse->code,
                'name'                 => $warehouse->name,
                'type_key'             => $this->getKey(),
                'type_label'           => $this->getLabel(),
                'badge_color'          => 'teal',
                'account_id'           => $warehouse->account_id,
                'party_type'           => Warehouse::class,
                'party_id'             => (string) $warehouse->id,
                'requires_cost_center' => false,
                'parent_account_name'  => $accountDisplay,
            ];
        })->toArray();
    }
}