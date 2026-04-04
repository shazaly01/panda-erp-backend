<?php

declare(strict_types=1);

namespace App\Modules\Accounting\DTO;

readonly class JournalEntryDetailDto
{
    public function __construct(
        public int $account_id,
        public float $debit,
        public float $credit,
        public ?int $cost_center_id = null,
        public ?string $description = null,

        public ?string $party_type = null,
        // التغيير هنا: من ?int إلى ?string
        // السبب: PHP قد تحول الأرقام الكبيرة جداً (DECIMAL 18) إلى Scientific Notation إذا كانت float،
        // أو تسبب Overflow إذا كانت int. النص هو الخيار الآمن للنقل.
        public ?string $party_id = null,
    ) {}
}
