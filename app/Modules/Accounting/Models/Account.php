<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kalnoy\Nestedset\NodeTrait;
use App\Modules\Accounting\Enums\AccountNature;
use App\Modules\Accounting\Database\Factories\AccountFactory;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\EntryStatus;
use App\Modules\Inventory\Models\Warehouse;

class Account extends Model
{
    use HasFactory, SoftDeletes, NodeTrait;

    protected $fillable = [
        'code',
        'name',
        'type',                  // asset, liability, equity, revenue, expense
        'nature',                // debit / credit (Enum)
        'currency_id',
        'parent_id',             // يأتي من NodeTrait
        'level',                 // مفيد أحياناً للتقارير (اختياري مع NestedSet)
        'is_transactional',      // هل يقبل قيود؟ (ليس حساب رئيسي)
        'requires_cost_center',
        'is_active',
        'description'            // تم توحيد الاسم مع Service و DTO
    ];

    protected $casts = [
        'type' => AccountType::class,
        'nature' => AccountNature::class,
        'is_transactional' => 'boolean',
        'requires_cost_center' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * منطق الحماية الذكي
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($account) {
            // 1. حماية الشجرة (موجودة في مكتبة NestedSet لكن نؤكد عليها)
            if ($account->children()->exists()) {
                // نستخدم Exception قياسي ليلتقطه الـ Handler
                abort(422, "لا يمكن حذف حساب ({$account->name}) لأنه يحتوي على حسابات فرعية.");
            }

            // 2. حماية البيانات المالية (تم تفعيلها الآن)
            if ($account->details()->exists()) {
                abort(422, "لا يمكن حذف الحساب ({$account->name}) لوجود قيود مالية مسجلة عليه. قم بتعطيله بدلاً من حذفه.");
            }

            // 3. حماية الارتباطات التشغيلية (خزائن/بنوك/مستودعات)
            if ($account->boxes()->exists() || $account->bankAccounts()->exists() || $account->warehouses()->exists()) {
                abort(422, "لا يمكن حذف الحساب لأنه مرتبط بخزينة أو حساب بنكي أو مستودع.");
            }
        });
    }

    // ============================================
    // النطاقات والدوال الحسابية (Scopes & Helpers)
    // ============================================

    /**
     * تحميل مجاميع المدين والدائن للقيود المُرحّلة فقط دفعة واحدة (لتفادي مشكلة N+1)
     */
    public function scopeWithPostedSums($query)
    {
        return $query
            ->withSum(['details as posted_debit_sum' => function ($detailQuery) {
                $detailQuery->whereHas('entry', function ($entryQuery) {
                    $entryQuery->where('status', EntryStatus::Posted);
                });
            }], 'debit')
            ->withSum(['details as posted_credit_sum' => function ($detailQuery) {
                $detailQuery->whereHas('entry', function ($entryQuery) {
                    $entryQuery->where('status', EntryStatus::Posted);
                });
            }], 'credit');
    }

    /**
     * حساب الرصيد الحالي الفعلي للحساب (المدين - الدائن) من القيود المُرحّلة فقط
     */
    public function getCurrentBalance(): float
    {
        if (
            array_key_exists('posted_debit_sum', $this->attributes) ||
            array_key_exists('posted_credit_sum', $this->attributes)
        ) {
            $debit = (float) ($this->attributes['posted_debit_sum'] ?? 0.0);
            $credit = (float) ($this->attributes['posted_credit_sum'] ?? 0.0);

            return round($debit - $credit, 4);
        }

        $totals = $this->details()
            ->whereHas('entry', function ($entryQuery) {
                $entryQuery->where('status', EntryStatus::Posted);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0.0);
        $credit = (float) ($totals->total_credit ?? 0.0);

        return round($debit - $credit, 4);
    }

    // ============================================
    // العلاقات (Relationships)
    // ============================================

    /**
     * تفاصيل القيود اليومية المرتبطة بهذا الحساب
     * هذه العلاقة ضرورية جداً لحساب الرصيد وكشف الحساب
     */
    public function details(): HasMany
    {
        return $this->hasMany(JournalEntryDetail::class, 'account_id');
    }

    /**
     * العملة الخاصة بالحساب
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * الخزائن المرتبطة بهذا الحساب (إن وجدت)
     */
    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'account_id');
    }

    /**
     * الحسابات البنكية المرتبطة بهذا الحساب (إن وجدت)
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'account_id');
    }

    /**
     * المستودعات المرتبطة بهذا الحساب
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'account_id');
    }

    protected static function newFactory()
    {
        return AccountFactory::new();
    }
}