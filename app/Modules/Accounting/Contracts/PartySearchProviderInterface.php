<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Contracts;

interface PartySearchProviderInterface
{
    /**
     * المفتاح الفريد للمزوّد (مثل: account, warehouse, employee)
     */
    public function getKey(): string;

    /**
     * الاسم التوضيحي للنوع باللغة العربية
     */
    public function getLabel(): string;

    /**
     * تنفيذ استعلام البحث وإرجاع مصفوفة مهيكلة بالصيغة الموحدة
     *
     * @param string $query نص البحث
     * @param int $limit الحد الأقصى للنتائج
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 10): array;
}