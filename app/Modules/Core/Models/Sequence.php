<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    /**
     * الجدول المرتبط بالموديل.
     *
     * @var string
     */
    protected $table = 'sequences';

    /**
     * الحقول القابلة للتعبئة.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'model',
        'branch_id',
        'format',
        'reset_frequency',
        'next_value',
        'current_year',
        'current_month',
    ];

    /**
     * الكاستينج (Casting) للتعامل الصحيح مع أنواع البيانات.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'next_value' => 'integer',
        'current_year' => 'integer',
        'current_month' => 'integer',
    ];
}
