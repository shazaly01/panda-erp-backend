<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Services\SearchProviders;

use App\Modules\Accounting\Contracts\PartySearchProviderInterface;
use App\Modules\Accounting\Models\Account;

class AccountSearchProvider implements PartySearchProviderInterface
{
    public function getKey(): string
    {
        return 'account';
    }

    public function getLabel(): string
    {
        return 'حساب مالي';
    }

    public function search(string $query, int $limit = 10): array
    {
        $accounts = Account::query()
            ->with('parent')
            ->where('is_active', true)
            ->where('is_transactional', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return $accounts->map(function (Account $account) {
            $parentDisplay = $account->parent
                ? "{$account->parent->code} - {$account->parent->name}"
                : 'دليل الحسابات';

            return [
                'key'                  => "acc_{$account->id}",
                'code'                 => (string) $account->code,
                'name'                 => $account->name,
                'type_key'             => $this->getKey(),
                'type_label'           => $this->getLabel(),
                'badge_color'          => 'blue',
                'account_id'           => $account->id,
                'party_type'           => null,
                'party_id'             => null,
                'requires_cost_center' => (bool) $account->requires_cost_center,
                'parent_account_name'  => $parentDisplay,
            ];
        })->toArray();
    }
}