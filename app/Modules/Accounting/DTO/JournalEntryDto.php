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
        public array $details,
        public ?string $description = null,
        public ?int $currency_id = null,
        public ?string $source = null,
        public ?string $reference_type = null,
        public ?int $reference_id = null,
    ) {}

    /**
     * دالة مساعدة للتحقق من أن المدخلات هي فعلاً كائنات DTO صحيحة
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