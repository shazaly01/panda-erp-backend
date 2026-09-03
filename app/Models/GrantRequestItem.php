<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\HR\Models\Department;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantRequestItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grant_request_id',
        'department_id',
        'item_name',
        'specifications',
        'quantity',
        'unit',
        'estimated_cost',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'estimated_cost' => 'decimal:2',
    ];

    public function grantRequest(): BelongsTo
    {
        return $this->belongsTo(GrantRequest::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}