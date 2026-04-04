<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Accounting\Enums\FiscalYearStatus;
use Carbon\Carbon;
use App\Modules\Accounting\Database\Factories\FiscalYearFactory;


class FiscalYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'status' => FiscalYearStatus::class,
    ];

    /**
     * دالة مساعدة للتحقق هل التاريخ يقع ضمن هذه السنة وهي مفتوحة؟
     */
    public static function checkDate(string $date): bool
    {
        $d = Carbon::parse($date);

        return static::where('status', FiscalYearStatus::Open)
            ->whereDate('start_date', '<=', $d)
            ->whereDate('end_date', '>=', $d)
            ->exists();
    }


    protected static function newFactory()
{
    return FiscalYearFactory::new();
}
}
