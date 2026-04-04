<?php

namespace App\Modules\Accounting\Database\Factories;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\EntrySource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            // ملاحظة: الرقم يولد عند الترحيل، لذا نتركه null في المسودة
            'entry_number' => null,
            'date' => '2025-01-15', // تاريخ داخل السنة المالية الافتراضية
            'status' => EntryStatus::Draft,
            'source' => EntrySource::Manual,
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(), // ينشئ مستخدم تلقائياً
        ];
    }

    // حالة خاصة لإنشاء قيد مرحل جاهز
    public function posted()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => EntryStatus::Posted,
                'entry_number' => 'JE-TEST-' . $this->faker->unique()->numberBetween(1000, 9999),
                'posted_at' => now(),
            ];
        });
    }
}
