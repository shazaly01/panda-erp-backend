<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne; // تأكد من إضافتها فوق
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\HR\Models\Contract;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active'
    ];



}
