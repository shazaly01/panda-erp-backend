<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
// نستخدم المسار الكامل للموديل المحاسبي لتجنب الأخطاء إذا لم يكن مستورداً
use App\Modules\Accounting\Models\JournalEntry;

class PayrollBatch extends Model
{
    use SoftDeletes;

    protected $table = 'payroll_batches';

    protected $fillable = [
        'date',             // تاريخ الاستحقاق (شهر الرواتب)
        'description',      // شرح الدفعة
        'status',           // draft, posted
        'total_amount',     // إجمالي الرواتب (اختياري للسرعة)
        'journal_entry_id', // ربط مع القيد المحاسبي (FK)
        'created_by',       // من قام بالاعتماد
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * المستخدم الذي قام باعتماد الرواتب
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * القيد المحاسبي المرتبط بهذه الدفعة
     * (مفيد جداً للوصول للقيد من شاشة الرواتب)
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
