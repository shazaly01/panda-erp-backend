<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntryDetail extends Model
{
    // لا نستخدم SoftDeletes هنا، لأن السطر يتبع الرأس.
    // إذا حذف الرأس، تحذف الأسطر نهائياً (Cascade).

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'cost_center_id',
        'party_type',
        'party_id',
        'debit',
        'credit',
        'description'
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
    ];

    /**
     * العودة لرأس القيد
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * الحساب المحاسبي
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * مركز التكلفة
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * الطرف المرتبط (عميل، مورد، موظف)
     * Polymorphic Relation
     */
    public function party(): MorphTo
    {
        return $this->morphTo();
    }
}
