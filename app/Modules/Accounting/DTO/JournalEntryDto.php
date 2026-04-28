<?php

declare(strict_types=1);

namespace App\Modules\Accounting\DTO;

use DateTimeInterface;

readonly class JournalEntryDto
{
    /**
     * @param JournalEntryDetailDto[] $details
     */
    public function __construct(
        public DateTimeInterface|string $date,
        public array $details, // مصفوفة من كائنات JournalEntryDetailDto
        public ?string $description = null,
        public ?int $currency_id = null,
        public ?string $source = null, // 🌟 الإضافة هنا: استقبال مصدر القيد الآلي
    ) {}

    /**
     * دالة مساعدة للتحقق من أن المدخلات هي فعلاً كائنات DTO صحيحة
     * (للحماية من إرسال مصفوفات عادية بالخطأ)
     */
    public function validateDetails(): void
    {
        foreach ($this->details as $detail) {
            if (! $detail instanceof JournalEntryDetailDto) {
                throw new \InvalidArgumentException('يجب أن تكون التفاصيل من نوع JournalEntryDetailDto');
            }
        }
    }
}
