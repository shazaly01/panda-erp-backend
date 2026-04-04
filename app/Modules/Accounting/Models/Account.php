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

            // 3. حماية الارتباطات التشغيلية (خزائن/بنوك)
            if ($account->boxes()->exists() || $account->bankAccounts()->exists()) {
                 abort(422, "لا يمكن حذف الحساب لأنه مرتبط بخزينة أو حساب بنكي.");
            }
        });
    }

    // ============================================
    // العلاقات (Relationships) - الجزء الناقص
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

    protected static function newFactory()
    {
        return AccountFactory::new();
    }
}
