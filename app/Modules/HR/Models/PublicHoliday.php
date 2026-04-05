<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicHoliday extends Model
{
    use SoftDeletes;

    protected $table = 'hr_public_holidays';

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_paid',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_paid' => 'boolean',
    ];
}
