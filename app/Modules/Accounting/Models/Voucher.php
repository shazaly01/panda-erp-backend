<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Accounting\Enums\VoucherType;
use App\Modules\Accounting\Enums\VoucherStatus;
use App\Modules\Accounting\Database\Factories\VoucherFactory;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'type',
        'number',
        'date',
        'payee_name',
        'description',
        'box_id',
        'bank_account_id',
        'currency_id',
        'exchange_rate',
        'amount',
        'status',
        'created_by',
        'posted_by',
        'posted_at'
    ];

    /**
     * تحويل البيانات تلقائياً لأنواعها المناسبة
     */
    protected $casts = [
        'date' => 'date',
        'exchange_rate' => 'float',
        'amount' => 'float',
        'type' => VoucherType::class,     // يربط مع ملف Enum
        'status' => VoucherStatus::class, // يربط مع ملف Enum
        'posted_at' => 'datetime',
    ];

    // --- العلاقات (Relationships) ---

    // 1. التفاصيل (السطور)
    public function details(): HasMany
    {
        return $this->hasMany(VoucherDetail::class);
    }

    // 2. الفرع (وهو في الحقيقة مركز تكلفة كما اتفقنا)
    public function branch(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'branch_id');
    }

    // 3. الخزينة (إذا كان الدفع نقداً)
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }

    // 4. البنك (إذا كان الدفع بنكياً)
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    // 5. العملة
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // --- دوال مساعدة (Helpers) ---

    // هل السند مرحل؟
    public function isPosted(): bool
    {
        return $this->status === VoucherStatus::Posted;
    }

    // هل السند مسودة؟
    public function isDraft(): bool
    {
        return $this->status === VoucherStatus::Draft;
    }

    protected static function newFactory()
    {
        return VoucherFactory::new();
    }
}
