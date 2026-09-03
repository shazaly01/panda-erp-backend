<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Modules\Core\Enums\PartnerType;
use App\Modules\Core\Enums\PartnerStatus;
use App\Modules\Core\Enums\PartnerTaxTreatment;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Currency;
use App\Modules\Accounting\Models\JournalEntryDetail;
use App\Modules\Accounting\Models\VoucherDetail;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_code',
        'type',
        'name',
        'commercial_name',
        'tax_number',
        'commercial_registry',
        'tax_treatment',
        'is_customer',
        'is_supplier',
        'status',
        'credit_limit',
        'credit_period_days',
        'currency_id',
        'receivable_account_id',
        'payable_account_id',
        'bank_name',
        'bank_account_number',
        'iban',
        'swift_code',
        'phone',
        'email',
        'address',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => PartnerType::class,
        'status' => PartnerStatus::class,
        'tax_treatment' => PartnerTaxTreatment::class,
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
        'credit_limit' => 'decimal:4',
        'credit_period_days' => 'integer',
        'currency_id' => 'integer',
        'receivable_account_id' => 'integer',
        'payable_account_id' => 'integer',
    ];

    /**
     * منطق الحماية والتحقق عند الحذف
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $partner): void {
            if ($partner->journalDetails()->exists()) {
                abort(422, "لا يمكن حذف الشريك ({$partner->name}) لوجود قيود يومية مرتبطة به. قم بتعطيل حسابه بدلاً من الحذف.");
            }

            if ($partner->voucherDetails()->exists()) {
                abort(422, "لا يمكن حذف الشريك ({$partner->name}) لوجود سندات صرف أو قبض مسجلة عليه. قم بتعطيل حسابه بدلاً من الحذف.");
            }
        });
    }

    // ============================================
    // نطاقات الاستعلام (Query Scopes)
    // ============================================

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('is_customer', true);
    }

    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->where('is_supplier', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PartnerStatus::ACTIVE);
    }

    // ============================================
    // دوال الفحص المساعد (Helper Methods)
    // ============================================

    public function isCustomer(): bool
    {
        return (bool) $this->is_customer;
    }

    public function isSupplier(): bool
    {
        return (bool) $this->is_supplier;
    }

    public function isBoth(): bool
    {
        return $this->isCustomer() && $this->isSupplier();
    }

    public function isActive(): bool
    {
        return $this->status === PartnerStatus::ACTIVE;
    }

    // ============================================
    // العلاقات (Relationships)
    // ============================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function journalDetails(): MorphMany
    {
        return $this->morphMany(JournalEntryDetail::class, 'party');
    }

    public function voucherDetails(): MorphMany
    {
        return $this->morphMany(VoucherDetail::class, 'party');
    }
}