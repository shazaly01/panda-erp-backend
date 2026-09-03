<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'target_organization',
        'title',
        'request_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'request_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GrantRequestItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}