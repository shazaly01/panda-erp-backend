<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMapping extends Model
{
    protected $fillable = [
        'key',
        'account_id',
        'branch_id',
        'name'
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
