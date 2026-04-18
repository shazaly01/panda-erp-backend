<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\HR\Enums\SalaryFrequency;

class PayGroup extends Model
{
    use SoftDeletes;

    protected $table = 'hr_pay_groups';

    protected $fillable = [
        'name',
        'frequency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'frequency' => SalaryFrequency::class,
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function payPeriods(): HasMany
    {
        return $this->hasMany(PayPeriod::class);
    }
}
