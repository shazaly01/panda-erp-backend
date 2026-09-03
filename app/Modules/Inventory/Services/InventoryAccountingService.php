<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\DTO\JournalEntryDetailDto;
use App\Modules\Accounting\DTO\JournalEntryDto;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountMappingService;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Inventory\Models\Adjustment;
use Illuminate\Support\Facades\DB;
use App\Modules\Inventory\Models\Transfer;

class InventoryAccountingService
{
    public function __construct(
        protected JournalEntryService $journalEntryService,
        protected AccountMappingService $accountMappingService
    ) {}

    /**
     * إنشاء وترحيل القيد المحاسبي للتسوية الجردية بحسب نوعها
     */
    public function createAdjustmentJournalEntry(Adjustment $adjustment): ?JournalEntry
    {
        // 1. التأكد من تحميل المستودع والبنود
        $adjustment->loadMissing(['warehouse', 'items']);

        // 2. جلب الحساب المالي للمخزون (الأولوية لحساب المستودع ثم الحساب العام من الخريطة)
        $inventoryAccountId = $adjustment->warehouse?->account_id 
            ?? $this->accountMappingService->getAccountId('inventory_asset');

        // 3. توجيه بناء القيد والوصف والمصدر بحسب نوع التسوية
        [$details, $entrySource, $description] = match ($adjustment->type) {
            'opening_balance' => [
                $this->buildOpeningBalanceDetails($adjustment, $inventoryAccountId),
                'opening',
                "قيد إثبات رصيد افتتاحي للمخزون رقم: {$adjustment->adjustment_number}"
            ],
            'damage' => [
                $this->buildDamageDetails($adjustment, $inventoryAccountId),
                'inventory',
                "قيد إتلاف بضاعة تالفة رقم: {$adjustment->adjustment_number}"
            ],
            'loss' => [
                $this->buildLossDetails($adjustment, $inventoryAccountId),
                'inventory',
                "قيد إثبات فاقد وعجز مخزني رقم: {$adjustment->adjustment_number}"
            ],
            default => [
                $this->buildStockAdjustmentDetails($adjustment, $inventoryAccountId),
                'inventory',
                "قيد تسوية مخزنية (جرد/عامة) رقم: {$adjustment->adjustment_number}"
            ],
        };

        // إذا لم تكن هناك مبالغ مالية تتطلب قيداً (التكلفة = 0 أو الفروقات = 0)
        if (empty($details)) {
            return null;
        }

        return DB::transaction(function () use ($adjustment, $details, $entrySource, $description) {
            $entryDto = new JournalEntryDto(
                date: $adjustment->adjustment_date,
                details: $details,
                description: $description,
                currency_id: null,
                source: $entrySource,
                reference_type: Adjustment::class,
                reference_id: $adjustment->id
            );

            // إنشاء القيد المالي
            $entry = $this->journalEntryService->createEntry($entryDto);

            // تترحيل القيد فورياً في الدفاتر المحاسبية
            return $this->journalEntryService->postEntry($entry);
        });
    }

    /**
     * بناء أطراف القيد في حالة الرصيد الافتتاحي (Opening Balance)
     * من حـ/ المخزون (مدين) إلى حـ/ الأرباح المبقاة - حقوق الملكية (دائن)
     *
     * @return JournalEntryDetailDto[]
     */
    protected function buildOpeningBalanceDetails(Adjustment $adjustment, int $inventoryAccountId): array
    {
        $equityAccountId = $this->accountMappingService->getAccountId('equity_retained_earnings');

        $totalOpeningAmount = 0.0;
        foreach ($adjustment->items as $item) {
            $totalOpeningAmount += (float) $item->total_cost;
        }

        $totalOpeningAmount = round($totalOpeningAmount, 4);

        if ($totalOpeningAmount <= 0) {
            return [];
        }

        return [
            // الطرف المدين: زيادة أصل المخزون
            new JournalEntryDetailDto(
                account_id: $inventoryAccountId,
                debit: $totalOpeningAmount,
                credit: 0.0,
                description: "إثبات رصيد افتتاحي للمخزون - مستند {$adjustment->adjustment_number}"
            ),
            // الطرف الدائن: حقوق الملكية / رأس المال
            new JournalEntryDetailDto(
                account_id: $equityAccountId,
                debit: 0.0,
                credit: $totalOpeningAmount,
                description: "المقابل الرأسمالي للرصيد الافتتاحي - مستند {$adjustment->adjustment_number}"
            ),
        ];
    }

    /**
     * بناء أطراف القيد في حالة البضاعة التالفة (Damage / Scrap)
     * من حـ/ خسائر بضاعة تالفة (مدين) إلى حـ/ المخزون (دائن)
     *
     * @return JournalEntryDetailDto[]
     */
    protected function buildDamageDetails(Adjustment $adjustment, int $inventoryAccountId): array
    {
        $damageAccountId = $this->accountMappingService->getAccountId('inventory_damage_expense');

        $totalDamageAmount = 0.0;
        foreach ($adjustment->items as $item) {
            $totalDamageAmount += (float) $item->total_cost;
        }

        $totalDamageAmount = round($totalDamageAmount, 4);

        if ($totalDamageAmount <= 0) {
            return [];
        }

        return [
            // الطرف المدين: إثبات خسائر البضاعة التالفة (مصروفات)
            new JournalEntryDetailDto(
                account_id: $damageAccountId,
                debit: $totalDamageAmount,
                credit: 0.0,
                description: "إثبات خسائر إتلاف بضاعة - مستند {$adjustment->adjustment_number}"
            ),
            // الطرف الدائن: تخفيض قيمة المخزون
            new JournalEntryDetailDto(
                account_id: $inventoryAccountId,
                debit: 0.0,
                credit: $totalDamageAmount,
                description: "تخفيض المخزون بقيمة البضاعة التالفة - مستند {$adjustment->adjustment_number}"
            ),
        ];
    }

    /**
     * بناء أطراف القيد في حالة الفاقد والعجز (Loss / Shrinkage)
     * من حـ/ خسائر فاقد وعجز المخزون (مدين) إلى حـ/ المخزون (دائن)
     *
     * @return JournalEntryDetailDto[]
     */
    protected function buildLossDetails(Adjustment $adjustment, int $inventoryAccountId): array
    {
        $lossAccountId = $this->accountMappingService->getAccountId('inventory_loss_expense');

        $totalLossAmount = 0.0;
        foreach ($adjustment->items as $item) {
            $totalLossAmount += (float) $item->total_cost;
        }

        $totalLossAmount = round($totalLossAmount, 4);

        if ($totalLossAmount <= 0) {
            return [];
        }

        return [
            // الطرف المدين: إثبات خسائر الفاقد والسرقات (مصروفات)
            new JournalEntryDetailDto(
                account_id: $lossAccountId,
                debit: $totalLossAmount,
                credit: 0.0,
                description: "إثبات خسائر عجز وفاقد مخزني - مستند {$adjustment->adjustment_number}"
            ),
            // الطرف الدائن: تخفيض قيمة المخزون
            new JournalEntryDetailDto(
                account_id: $inventoryAccountId,
                debit: 0.0,
                credit: $totalLossAmount,
                description: "تخفيض المخزون بقيمة العجز والفاقد - مستند {$adjustment->adjustment_number}"
            ),
        ];
    }

    /**
     * بناء أطراف القيد في حالة التسويات الجردية الدورية والعامة (فائض / عجز)
     *
     * @return JournalEntryDetailDto[]
     */
    protected function buildStockAdjustmentDetails(Adjustment $adjustment, int $inventoryAccountId): array
    {
        $totalGain = 0.0; // إجمالي الفائض (الزيادة)
        $totalLoss = 0.0; // إجمالي العجز (النقص)

        foreach ($adjustment->items as $item) {
            $diff = (float) $item->quantity_difference;
            $itemCost = (float) $item->total_cost;

            if ($diff > 0) {
                $totalGain += $itemCost;
            } elseif ($diff < 0) {
                $totalLoss += $itemCost;
            }
        }

        $totalGain = round($totalGain, 4);
        $totalLoss = round($totalLoss, 4);

        $details = [];

        // 1. معالجة الفائض (الزيادة في الجرد):
        // من حـ/ المخزون (مدين) إلى حـ/ أرباح وفائض الجرد (دائن)
        if ($totalGain > 0) {
            $gainAccountId = $this->accountMappingService->getAccountId('inventory_adjustment_gain');

            $details[] = new JournalEntryDetailDto(
                account_id: $inventoryAccountId,
                debit: $totalGain,
                credit: 0.0,
                description: "إثبات فائض جرد مخزني - مستند {$adjustment->adjustment_number}"
            );

            $details[] = new JournalEntryDetailDto(
                account_id: $gainAccountId,
                debit: 0.0,
                credit: $totalGain,
                description: "أرباح تسويات وفائض جرد - مستند {$adjustment->adjustment_number}"
            );
        }

        // 2. معالجة العجز (النقص في الجرد):
        // من حـ/ خسائر وعجز الجرد (مدين) إلى حـ/ المخزون (دائن)
        if ($totalLoss > 0) {
            $lossAccountId = $this->accountMappingService->getAccountId('inventory_adjustment_loss');

            $details[] = new JournalEntryDetailDto(
                account_id: $lossAccountId,
                debit: $totalLoss,
                credit: 0.0,
                description: "إثبات عجز جرد مخزني - مستند {$adjustment->adjustment_number}"
            );

            $details[] = new JournalEntryDetailDto(
                account_id: $inventoryAccountId,
                debit: 0.0,
                credit: $totalLoss,
                description: "تخفيض المخزون بفارق عجز الجرد - مستند {$adjustment->adjustment_number}"
            );
        }

        return $details;
    }






    /**
     * إنشاء وترحيل القيد المحاسبي لعملية التحويل بين المستودعات في حال اختلاف الحساب المالي للمستودعين.
     */
    public function createTransferJournalEntry(\App\Modules\Inventory\Models\Transfer $transfer): ?\App\Modules\Accounting\Models\JournalEntry
    {
        // 1. التأكد من تحميل المستودعات والبنود
        $transfer->loadMissing(['fromWarehouse', 'toWarehouse', 'items']);

        // 2. جلب الحساب المالي لمخزون المستودع المصدر والمستودع الوجهة
        $defaultInventoryAccountId = $this->accountMappingService->getAccountId('inventory_asset');

        $fromAccountId = $transfer->fromWarehouse?->account_id ?? $defaultInventoryAccountId;
        $toAccountId = $transfer->toWarehouse?->account_id ?? $defaultInventoryAccountId;

        // إذا كان الحساب المالي متطابقاً، لا داعي لإنشاء قيد مالي (الحركة أثرها مخزني فقط)
        if ($fromAccountId === $toAccountId) {
            return null;
        }

        // 3. حساب إجمالي تكلفة البضاعة المحولة
        $totalCost = 0.0;
        foreach ($transfer->items as $item) {
            $totalCost += (float) $item->total_cost;
        }

        $totalCost = round($totalCost, 4);

        if ($totalCost <= 0) {
            return null;
        }

        // 4. بناء أطراف القيد:
        // مدين: زيادة أصل المخزون في المستودع الوجهة
        // دائن: تخفيض أصل المخزون في المستودع المصدر
        $details = [
            new JournalEntryDetailDto(
                account_id: $toAccountId,
                debit: $totalCost,
                credit: 0.0,
                description: "إثبات تحويل مخزني وارد للمستودع ({$transfer->toWarehouse?->name}) - مستند رقم: {$transfer->transfer_number}"
            ),
            new JournalEntryDetailDto(
                account_id: $fromAccountId,
                debit: 0.0,
                credit: $totalCost,
                description: "إثبات تحويل مخزني صادر من المستودع ({$transfer->fromWarehouse?->name}) - مستند رقم: {$transfer->transfer_number}"
            ),
        ];

        return DB::transaction(function () use ($transfer, $details) {
            $entryDto = new JournalEntryDto(
                date: $transfer->transfer_date,
                details: $details,
                description: "قيد تحويل مخزني رقم: {$transfer->transfer_number}",
                currency_id: null,
                source: 'inventory',
                reference_type: \App\Modules\Inventory\Models\Transfer::class,
                reference_id: $transfer->id
            );

            // إنشاء القيد المالي
            $entry = $this->journalEntryService->createEntry($entryDto);

            // ترحيل القيد فورياً في الدفاتر
            return $this->journalEntryService->postEntry($entry);
        });
    }
}