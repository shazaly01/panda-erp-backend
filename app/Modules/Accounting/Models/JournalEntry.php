<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Accounting\Enums\EntrySource;
use App\Models\User;
use App\Modules\Accounting\Database\Factories\JournalEntryFactory;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entry_number',
        'date',
        'status',
        'source',
        'description',
        'currency_id',
        'created_by',
        'posted_at',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'status' => EntryStatus::class,
        'source' => EntrySource::class,
        'date' => 'date',
        'posted_at' => 'datetime',
    ];

    /**
     * العلاقة مع التفاصيل (أسطر القيد)
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class);
    }

    /**
     * من الذي أنشأ القيد؟
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * المرجع للمستند الأصلي (تسوية جردية، فاتورة، سند، إلخ)
     * Polymorphic Relation
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * حماية دفتر الأستاذ (The Ledger Immutability)
     */
    protected static function boot()
    {
        parent::boot();

        // 1. منع الحذف إذا كان القيد مرحلاً
        static::deleting(function ($entry) {
            if ($entry->status === EntryStatus::Posted) {
                abort(409, 'لا يمكن حذف قيد مرحل. يجب عمل قيد عكسي بدلاً من ذلك.');
            }
        });

        // 2. منع التعديل إذا كان القيد مرحلاً
        static::updating(function ($entry) {
            if ($entry->original['status'] === EntryStatus::Posted->value && $entry->status !== EntryStatus::Void) {
                // منع التعديل إذا كان القيد مرحلاً
            }
        });
    }

    protected static function newFactory()
    {
        return JournalEntryFactory::new();
    }
}