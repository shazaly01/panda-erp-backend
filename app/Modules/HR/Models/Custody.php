<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Custody extends Model
{
    use SoftDeletes;

    protected $table = 'hr_custodies';

    protected $fillable = [
        'employee_id',
        'item_name',
        'reference_number',
        'received_date',
        'return_date',
        'status',
        'estimated_value',
        'notes',
    ];

    protected $casts = [
        'received_date' => 'date',
        'return_date' => 'date',
        'estimated_value' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
